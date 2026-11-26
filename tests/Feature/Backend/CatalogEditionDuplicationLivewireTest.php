<?php

use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogChangeLog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // Gebruik Nederlands als testtaal.
    app()->setLocale('nl');

    // Maak een administrator aan.
    $this->admin = User::factory()->create([
        'name' => 'Administrator Livewire Duplicatie',
        'is_admin' => true,
    ]);

    // Maak een gewone gebruiker aan.
    $this->user = User::factory()->create([
        'name' => 'Gebruiker Livewire Duplicatie',
        'is_admin' => false,
    ]);

    // Maak een testland aan.
    $this->country = Country::create([
        'name' => 'Testland Livewire Duplicatie',
        'iso_code' => 'LD',
        'entity_type' => 'country',
        'slug' => 'testland-livewire-duplicatie',
        'is_active' => true,
    ]);

    // Maak een testcatalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Livewire Duplicatie',
        'abbreviation' => 'TLD',
    ]);

    // Koppel het testland aan de catalogus.
    $this->catalog->countries()->attach($this->country->id);

    // Maak een bestaande broneditie aan.
    $this->sourceEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingscategorie Postfris aan.
    $this->postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingscategorie Gestempeld aan.
    $this->gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Bewaar de exacte Livewire-componentnaam.
    $this->componentName =
        'backend.catalog-editions.create-catalog-edition';

    // Haal de geschiedenisservice uit de servicecontainer op.
    $this->history = app(CatalogChangeHistoryService::class);
});

test('een nieuwe cataloguseditie zonder duplicatie wordt opgeslagen zonder geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Bewaar de oorspronkelijke gegevens van de broneditie.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(0);
    expect(StampCatalogValue::count())->toBe(0);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Maak een nieuwe cataloguseditie aan zonder broneditie.
    Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Nieuwe cataloguseditie zonder duplicatie.')
        ->assertSet('source_edition_id', null)
        ->assertSet('copy_catalog_values', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer het aantal catalogusedities.
    expect(CatalogEdition::count())->toBe(2);

    // Haal de nieuw aangemaakte cataloguseditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Controleer de opgeslagen editiegegevens.
    expect($targetEdition->id)->not->toBe($this->sourceEdition->id);
    expect($targetEdition->catalog_id)->toBe($this->catalog->id);
    expect($targetEdition->edition_name)->toBe('Editie 2027');
    expect((int) $targetEdition->edition_year)->toBe(2027);
    expect($targetEdition->currency)->toBe('EUR');
    expect($targetEdition->notes)
        ->toBe('Nieuwe cataloguseditie zonder duplicatie.');

    expect(
        Carbon::parse(
            $targetEdition->getRawOriginal('publication_date')
        )->toDateString()
    )->toBe('2027-01-15');

    // Controleer dat de broneditie ongewijzigd is gebleven.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    // Controleer dat er geen catalogusgegevens zijn gekopieerd.
    expect($targetEdition->stampEntries()->count())->toBe(0);
    expect($targetEdition->stampValues()->count())->toBe(0);
    expect(StampCatalogEntry::count())->toBe(0);
    expect(StampCatalogValue::count())->toBe(0);

    // Controleer dat er geen geschiedenis is geregistreerd.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('een duplicatieformulier wordt voorbereid zonder gegevens op te slaan voordat de gebruiker bevestigt', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-001',
        'notes' => 'Oorspronkelijke catalogusvermelding.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Nieuwe editie vanuit de broneditie.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Er mag nog geen duplicatiebevestiging aanwezig zijn.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor, maar bevestig deze nog niet.
    $component
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    // Controleer dat een bevestigingshash is opgeslagen.
    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat er nog geen nieuwe cataloguseditie bestaat.
    expect(CatalogEdition::count())->toBe(1);

    $this->assertDatabaseMissing('catalog_editions', [
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    // Controleer dat er nog geen catalogusgegevens zijn gekopieerd.
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($sourceEntry->fresh()->getAttributes())
        ->toEqual($originalSourceEntry);

    expect($sourceValue->fresh()->getAttributes())
        ->toEqual($originalSourceValue);

    // Controleer dat er nog geen geschiedenis is geregistreerd.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat er geen actieve geschiedenisbatch bestaat.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('een voorbereide duplicatie kan worden geannuleerd zonder gegevensopslag en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-002',
        'notes' => 'Catalogusvermelding voor geannuleerde duplicatie.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '35.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor geannuleerde duplicatie.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-02-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Deze duplicatie wordt geannuleerd.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat er nog geen bevestigingshash bestaat.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatie op bevestiging wacht.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(session()->get($sessionKey))->toBeString();

    // Controleer dat er nog geen gegevens zijn opgeslagen.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Annuleer de voorbereide duplicatie.
    $component
        ->call('cancelDuplication')
        ->assertHasNoErrors();

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();
    expect(session()->get($sessionKey))->toBeNull();

    // Controleer dat er geen nieuwe cataloguseditie bestaat.
    expect(CatalogEdition::count())->toBe(1);

    $this->assertDatabaseMissing('catalog_editions', [
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    // Controleer dat er geen catalogusgegevens zijn gekopieerd.
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($sourceEntry->fresh()->getAttributes())
        ->toEqual($originalSourceEntry);

    expect($sourceValue->fresh()->getAttributes())
        ->toEqual($originalSourceValue);

    // Controleer dat er geen geschiedenis is geregistreerd.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('een cataloguseditie wordt zonder cataloguswaarden gedupliceerd inclusief geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak twee postzegels voor de broneditie aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak de eerste catalogusvermelding aan.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'LW-003A',
        'notes' => 'Eerste oorspronkelijke catalogusvermelding.',
    ]);

    // Maak de tweede catalogusvermelding aan.
    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'LW-003B',
        'notes' => 'Tweede oorspronkelijke catalogusvermelding.',
    ]);

    // Maak een cataloguswaarde voor de eerste vermelding aan.
    $firstSourceValue = $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '45.50',
        'currency' => 'EUR',
        'notes' => 'Eerste oorspronkelijke cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde voor de tweede vermelding aan.
    $secondSourceValue = $secondSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Tweede oorspronkelijke cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalFirstEntry = $firstSourceEntry->fresh()->getAttributes();
    $originalSecondEntry = $secondSourceEntry->fresh()->getAttributes();
    $originalFirstValue = $firstSourceValue->fresh()->getAttributes();
    $originalSecondValue = $secondSourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-03-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie zonder cataloguswaarden.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', false);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatie op bevestiging wacht.
    expect(session()->has($sessionKey))->toBeTrue();

    // Controleer dat er nog geen gegevens zijn opgeslagen.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat de nieuwe cataloguseditie is aangemaakt.
    expect(CatalogEdition::count())->toBe(2);

    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    expect($targetEdition->id)->not->toBe($this->sourceEdition->id);
    expect($targetEdition->catalog_id)->toBe($this->catalog->id);
    expect($targetEdition->edition_name)->toBe('Editie 2027');
    expect((int) $targetEdition->edition_year)->toBe(2027);
    expect($targetEdition->currency)->toBe('EUR');
    expect($targetEdition->notes)
        ->toBe('Duplicatie zonder cataloguswaarden.');

    // Controleer de publicatiedatum.
    expect(
        Carbon::parse(
            $targetEdition->getRawOriginal('publication_date')
        )->toDateString()
    )->toBe('2027-03-15');

    // Controleer het aantal catalogusvermeldingen en waarden.
    expect(StampCatalogEntry::count())->toBe(4);
    expect(StampCatalogValue::count())->toBe(2);
    expect($targetEdition->stampEntries()->count())->toBe(2);
    expect($targetEdition->stampValues()->count())->toBe(0);

    // Haal de eerste gekopieerde catalogusvermelding op.
    $firstTargetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $firstStamp->id)
        ->sole();

    // Haal de tweede gekopieerde catalogusvermelding op.
    $secondTargetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $secondStamp->id)
        ->sole();

    // Controleer de eerste gekopieerde catalogusvermelding.
    expect($firstTargetEntry->id)->not->toBe($firstSourceEntry->id);
    expect($firstTargetEntry->catalog_edition_id)->toBe($targetEdition->id);
    expect($firstTargetEntry->stamp_id)->toBe($firstStamp->id);
    expect($firstTargetEntry->catalog_number)->toBe('LW-003A');
    expect($firstTargetEntry->notes)
        ->toBe('Eerste oorspronkelijke catalogusvermelding.');
    expect($firstTargetEntry->values()->count())->toBe(0);

    // Controleer de tweede gekopieerde catalogusvermelding.
    expect($secondTargetEntry->id)->not->toBe($secondSourceEntry->id);
    expect($secondTargetEntry->catalog_edition_id)->toBe($targetEdition->id);
    expect($secondTargetEntry->stamp_id)->toBe($secondStamp->id);
    expect($secondTargetEntry->catalog_number)->toBe('LW-003B');
    expect($secondTargetEntry->notes)
        ->toBe('Tweede oorspronkelijke catalogusvermelding.');
    expect($secondTargetEntry->values()->count())->toBe(0);

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($firstSourceEntry->fresh()->getAttributes())
        ->toEqual($originalFirstEntry);

    expect($secondSourceEntry->fresh()->getAttributes())
        ->toEqual($originalSecondEntry);

    expect($firstSourceValue->fresh()->getAttributes())
        ->toEqual($originalFirstValue);

    expect($secondSourceValue->fresh()->getAttributes())
        ->toEqual($originalSecondValue);

    // Controleer het aantal geschiedenisregistraties.
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(2);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de belangrijkste batchgegevens.
    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->user_id)->toBe($this->admin->id);
    expect($batch->user_name)->toBe($this->admin->name);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->catalog_name)->toBe($this->catalog->name);
    expect($batch->catalog_edition_name)->toBe('Editie 2027');
    expect($batch->logs()->count())->toBe(2);

    // Controleer dat uitsluitend catalogusvermeldingen zijn geregistreerd.
    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('action', 'created')
            ->count()
    )->toBe(2);

    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count()
    )->toBe(0);

    // Controleer de wijzigingslog van de eerste vermelding.
    $firstLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $firstTargetEntry->id)
        ->sole();

    expect($firstLog->catalog_edition_id)->toBe($targetEdition->id);
    expect($firstLog->stamp_id)->toBe($firstStamp->id);
    expect($firstLog->action)->toBe('created');
    expect($firstLog->old_values)->toBeNull();
    expect($firstLog->new_values)
        ->toEqual($firstTargetEntry->fresh()->getAttributes());

    // Controleer de wijzigingslog van de tweede vermelding.
    $secondLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $secondTargetEntry->id)
        ->sole();

    expect($secondLog->catalog_edition_id)->toBe($targetEdition->id);
    expect($secondLog->stamp_id)->toBe($secondStamp->id);
    expect($secondLog->action)->toBe('created');
    expect($secondLog->old_values)->toBeNull();
    expect($secondLog->new_values)
        ->toEqual($secondTargetEntry->fresh()->getAttributes());

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('een cataloguseditie wordt inclusief cataloguswaarden gedupliceerd met volledige geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak twee postzegels voor de broneditie aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'LW-004A',
        'notes' => 'Eerste vermelding met cataloguswaarden.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'LW-004B',
        'notes' => 'Tweede vermelding met cataloguswaarden.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $firstSourceValue = $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '125.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde van nul aan.
    $secondSourceValue = $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde van nul.',
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde in een andere valuta aan.
    $thirdSourceValue = $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '35.50',
        'currency' => 'USD',
        'notes' => 'Oorspronkelijke cataloguswaarde in dollars.',
        'needs_review' => true,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalFirstEntry = $firstSourceEntry->fresh()->getAttributes();
    $originalSecondEntry = $secondSourceEntry->fresh()->getAttributes();
    $originalFirstValue = $firstSourceValue->fresh()->getAttributes();
    $originalSecondValue = $secondSourceValue->fresh()->getAttributes();
    $originalThirdValue = $thirdSourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(3);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-04-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie inclusief cataloguswaarden.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    expect(session()->has($sessionKey))->toBeTrue();

    // Controleer dat er vóór bevestiging niets is opgeslagen.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(3);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Haal de nieuw aangemaakte cataloguseditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Controleer de nieuwe cataloguseditie.
    expect(CatalogEdition::count())->toBe(2);
    expect($targetEdition->id)->not->toBe($this->sourceEdition->id);
    expect($targetEdition->catalog_id)->toBe($this->catalog->id);
    expect($targetEdition->edition_name)->toBe('Editie 2027');
    expect((int) $targetEdition->edition_year)->toBe(2027);
    expect($targetEdition->currency)->toBe('EUR');
    expect($targetEdition->notes)
        ->toBe('Duplicatie inclusief cataloguswaarden.');

    expect(
        Carbon::parse(
            $targetEdition->getRawOriginal('publication_date')
        )->toDateString()
    )->toBe('2027-04-15');

    // Controleer de aantallen gekopieerde gegevens.
    expect(StampCatalogEntry::count())->toBe(4);
    expect(StampCatalogValue::count())->toBe(6);
    expect($targetEdition->stampEntries()->count())->toBe(2);
    expect($targetEdition->stampValues()->count())->toBe(3);

    // Haal de gekopieerde catalogusvermeldingen op.
    $firstTargetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $firstStamp->id)
        ->sole();

    $secondTargetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $secondStamp->id)
        ->sole();

    // Controleer de eerste gekopieerde catalogusvermelding.
    expect($firstTargetEntry->id)->not->toBe($firstSourceEntry->id);
    expect($firstTargetEntry->catalog_edition_id)->toBe($targetEdition->id);
    expect($firstTargetEntry->stamp_id)->toBe($firstStamp->id);
    expect($firstTargetEntry->catalog_number)->toBe('LW-004A');
    expect($firstTargetEntry->notes)
        ->toBe('Eerste vermelding met cataloguswaarden.');
    expect($firstTargetEntry->values()->count())->toBe(2);

    // Controleer de tweede gekopieerde catalogusvermelding.
    expect($secondTargetEntry->id)->not->toBe($secondSourceEntry->id);
    expect($secondTargetEntry->catalog_edition_id)->toBe($targetEdition->id);
    expect($secondTargetEntry->stamp_id)->toBe($secondStamp->id);
    expect($secondTargetEntry->catalog_number)->toBe('LW-004B');
    expect($secondTargetEntry->notes)
        ->toBe('Tweede vermelding met cataloguswaarden.');
    expect($secondTargetEntry->values()->count())->toBe(1);

    // Haal de drie gekopieerde cataloguswaarden op.
    $firstTargetValue = $firstTargetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    $secondTargetValue = $firstTargetEntry->values()
        ->where('condition_id', $this->gestempeld->id)
        ->sole();

    $thirdTargetValue = $secondTargetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    // Controleer de eerste gekopieerde cataloguswaarde.
    expect($firstTargetValue->id)->not->toBe($firstSourceValue->id);
    expect($firstTargetValue->stamp_catalog_entry_id)
        ->toBe($firstTargetEntry->id);
    expect($firstTargetValue->condition_id)->toBe($this->postfris->id);
    expect($firstTargetValue->catalog_value)->toBe('125.75');
    expect($firstTargetValue->currency)->toBe('EUR');
    expect($firstTargetValue->notes)
        ->toBe('Oorspronkelijke postfrisse cataloguswaarde.');
    expect($firstTargetValue->needs_review)->toBeTrue();

    // Controleer dat de cataloguswaarde van nul behouden blijft.
    expect($secondTargetValue->id)->not->toBe($secondSourceValue->id);
    expect($secondTargetValue->stamp_catalog_entry_id)
        ->toBe($firstTargetEntry->id);
    expect($secondTargetValue->condition_id)->toBe($this->gestempeld->id);
    expect($secondTargetValue->catalog_value)->toBe('0.00');
    expect($secondTargetValue->currency)->toBe('EUR');
    expect($secondTargetValue->notes)
        ->toBe('Oorspronkelijke cataloguswaarde van nul.');
    expect($secondTargetValue->needs_review)->toBeTrue();

    // Controleer de gekopieerde cataloguswaarde in dollars.
    expect($thirdTargetValue->id)->not->toBe($thirdSourceValue->id);
    expect($thirdTargetValue->stamp_catalog_entry_id)
        ->toBe($secondTargetEntry->id);
    expect($thirdTargetValue->condition_id)->toBe($this->postfris->id);
    expect($thirdTargetValue->catalog_value)->toBe('35.50');
    expect($thirdTargetValue->currency)->toBe('USD');
    expect($thirdTargetValue->notes)
        ->toBe('Oorspronkelijke cataloguswaarde in dollars.');
    expect($thirdTargetValue->needs_review)->toBeTrue();

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($firstSourceEntry->fresh()->getAttributes())
        ->toEqual($originalFirstEntry);

    expect($secondSourceEntry->fresh()->getAttributes())
        ->toEqual($originalSecondEntry);

    expect($firstSourceValue->fresh()->getAttributes())
        ->toEqual($originalFirstValue);

    expect($secondSourceValue->fresh()->getAttributes())
        ->toEqual($originalSecondValue);

    expect($thirdSourceValue->fresh()->getAttributes())
        ->toEqual($originalThirdValue);

    // Controleer de oorspronkelijke controlemarkeringen.
    expect($firstSourceValue->fresh()->needs_review)->toBeFalse();
    expect($secondSourceValue->fresh()->needs_review)->toBeFalse();
    expect($thirdSourceValue->fresh()->needs_review)->toBeTrue();

    // Controleer het aantal geschiedenisregistraties.
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(5);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de volledige batchmetadata.
    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->user_id)->toBe($this->admin->id);
    expect($batch->user_name)->toBe($this->admin->name);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->description)
        ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026');
    expect($batch->catalog_name)->toBe($this->catalog->name);
    expect($batch->catalog_edition_name)->toBe('Editie 2027');
    expect($batch->logs()->count())->toBe(5);

    // Controleer het aantal logs per recordtype.
    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count()
    )->toBe(2);

    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count()
    )->toBe(3);

    expect(
        $batch->logs()
            ->where('action', 'created')
            ->count()
    )->toBe(5);

    // Controleer beide wijzigingslogs van catalogusvermeldingen.
    foreach ([$firstTargetEntry, $secondTargetEntry] as $targetEntry) {
        $log = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $targetEntry->id)
            ->sole();

        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($targetEdition->id);
        expect($log->stamp_id)->toBe($targetEntry->stamp_id);
        expect($log->condition_id)->toBeNull();
        expect($log->action)->toBe('created');
        expect($log->old_values)->toBeNull();
        expect($log->new_values)
            ->toEqual($targetEntry->fresh()->getAttributes());
    }

    // Controleer alle wijzigingslogs van cataloguswaarden.
    foreach ([
        $firstTargetValue,
        $secondTargetValue,
        $thirdTargetValue,
    ] as $targetValue) {
        $log = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $targetValue->id)
            ->sole();

        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($targetEdition->id);
        expect($log->stamp_id)
            ->toBe($targetValue->catalogEntry->stamp_id);
        expect($log->condition_id)->toBe($targetValue->condition_id);
        expect($log->action)->toBe('created');
        expect($log->old_values)->toBeNull();
        expect($log->new_values)
            ->toEqual($targetValue->fresh()->getAttributes());
    }

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('een lege broneditie kan worden gedupliceerd zonder dat een lege geschiedenisbatch achterblijft', function () {
    $this->actingAs($this->admin);

    // Bewaar een volledige snapshot van de oorspronkelijke broneditie.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();

    // Controleer dat de broneditie volledig leeg is.
    expect($this->sourceEdition->stampEntries()->count())->toBe(0);
    expect($this->sourceEdition->stampValues()->count())->toBe(0);

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(0);
    expect(StampCatalogValue::count())->toBe(0);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-05-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie van een lege broneditie.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat er nog geen bevestigingshash bestaat.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie van de lege broneditie voor.
    $component
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    // Controleer dat de duplicatie op bevestiging wacht.
    expect(session()->has($sessionKey))->toBeTrue();

    // Controleer dat er vóór bevestiging niets is opgeslagen.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(0);
    expect(StampCatalogValue::count())->toBe(0);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat de nieuwe cataloguseditie is aangemaakt.
    expect(CatalogEdition::count())->toBe(2);

    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Controleer de gegevens van de nieuwe cataloguseditie.
    expect($targetEdition->id)->not->toBe($this->sourceEdition->id);
    expect($targetEdition->catalog_id)->toBe($this->catalog->id);
    expect($targetEdition->edition_name)->toBe('Editie 2027');
    expect((int) $targetEdition->edition_year)->toBe(2027);
    expect($targetEdition->currency)->toBe('EUR');
    expect($targetEdition->notes)
        ->toBe('Duplicatie van een lege broneditie.');

    // Controleer de publicatiedatum.
    expect(
        Carbon::parse(
            $targetEdition->getRawOriginal('publication_date')
        )->toDateString()
    )->toBe('2027-05-15');

    // Controleer dat de nieuwe cataloguseditie leeg is.
    expect($targetEdition->stampEntries()->count())->toBe(0);
    expect($targetEdition->stampValues()->count())->toBe(0);

    // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    // Controleer dat de oorspronkelijke broneditie leeg is gebleven.
    expect($this->sourceEdition->stampEntries()->count())->toBe(0);
    expect($this->sourceEdition->stampValues()->count())->toBe(0);

    // Controleer dat er geen catalogusgegevens zijn aangemaakt.
    expect(StampCatalogEntry::count())->toBe(0);
    expect(StampCatalogValue::count())->toBe(0);

    // Controleer dat er geen lege geschiedenisbatch is achtergebleven.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    $this->assertDatabaseMissing('catalog_change_batches', [
        'catalog_edition_id' => $targetEdition->id,
    ]);

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();
});

test('alle oorspronkelijke brongegevens blijven volledig ongewijzigd tijdens een duplicatie vanuit Livewire', function () {
    $this->actingAs($this->admin);

    // Maak twee postzegels voor de broneditie aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding met bijzondere tekens aan.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'LW-005/Ä',
        'notes' => "Nederland – België – Deutschland\nÉdition spéciale – España",
    ]);

    // Maak een catalogusvermelding zonder notities aan.
    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'LW-005/B',
        'notes' => null,
    ]);

    // Maak een cataloguswaarde met meertalige notities aan.
    $firstSourceValue = $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '175.95',
        'currency' => 'EUR',
        'notes' => "Postfris – Neuf sans charnière\nMNH – Postfrisch",
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde zonder notities aan.
    $secondSourceValue = $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'CHF',
        'notes' => null,
        'needs_review' => true,
    ]);

    // Maak een cataloguswaarde in dollars aan.
    $thirdSourceValue = $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '89.50',
        'currency' => 'USD',
        'notes' => "Bijzondere waardering\nSpecial valuation",
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van alle oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();

    $originalFirstStamp = $firstStamp->fresh()->getAttributes();
    $originalSecondStamp = $secondStamp->fresh()->getAttributes();

    $originalFirstEntry = $firstSourceEntry->fresh()->getAttributes();
    $originalSecondEntry = $secondSourceEntry->fresh()->getAttributes();

    $originalFirstValue = $firstSourceValue->fresh()->getAttributes();
    $originalSecondValue = $secondSourceValue->fresh()->getAttributes();
    $originalThirdValue = $thirdSourceValue->fresh()->getAttributes();

    $originalPostfris = $this->postfris->fresh()->getAttributes();
    $originalGestempeld = $this->gestempeld->fresh()->getAttributes();
    $originalCountry = $this->country->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(3);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste timestampwijzigingen
    // tijdens de duplicatie zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-06-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie met volledig behoud van brongegevens.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        expect(session()->has($sessionKey))->toBeTrue();

        // Bevestig de duplicatie.
        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('backend.catalogs.editions.index', $this->catalog)
            );

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();

        // Haal de nieuwe cataloguseditie op.
        $targetEdition = $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->sole();

        // Controleer de aantallen na duplicatie.
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(4);
        expect(StampCatalogValue::count())->toBe(6);

        expect($targetEdition->stampEntries()->count())->toBe(2);
        expect($targetEdition->stampValues()->count())->toBe(3);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat beide oorspronkelijke postzegels ongewijzigd zijn.
        expect($firstStamp->fresh()->getAttributes())
            ->toEqual($originalFirstStamp);

        expect($secondStamp->fresh()->getAttributes())
            ->toEqual($originalSecondStamp);

        // Controleer dat beide bronvermeldingen ongewijzigd zijn.
        expect($firstSourceEntry->fresh()->getAttributes())
            ->toEqual($originalFirstEntry);

        expect($secondSourceEntry->fresh()->getAttributes())
            ->toEqual($originalSecondEntry);

        // Controleer dat alle oorspronkelijke cataloguswaarden
        // volledig ongewijzigd zijn.
        expect($firstSourceValue->fresh()->getAttributes())
            ->toEqual($originalFirstValue);

        expect($secondSourceValue->fresh()->getAttributes())
            ->toEqual($originalSecondValue);

        expect($thirdSourceValue->fresh()->getAttributes())
            ->toEqual($originalThirdValue);

        // Controleer dat beide waarderingscategorieën ongewijzigd zijn.
        expect($this->postfris->fresh()->getAttributes())
            ->toEqual($originalPostfris);

        expect($this->gestempeld->fresh()->getAttributes())
            ->toEqual($originalGestempeld);

        // Controleer dat het oorspronkelijke land ongewijzigd is.
        expect($this->country->fresh()->getAttributes())
            ->toEqual($originalCountry);

        // Controleer de oorspronkelijke controlemarkeringen.
        expect($firstSourceValue->fresh()->needs_review)->toBeFalse();
        expect($secondSourceValue->fresh()->needs_review)->toBeTrue();
        expect($thirdSourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer de oorspronkelijke tijdstempels.
        expect($firstSourceEntry->fresh()->getRawOriginal('updated_at'))
            ->toBe($originalFirstEntry['updated_at']);

        expect($secondSourceEntry->fresh()->getRawOriginal('updated_at'))
            ->toBe($originalSecondEntry['updated_at']);

        expect($firstSourceValue->fresh()->getRawOriginal('updated_at'))
            ->toBe($originalFirstValue['updated_at']);

        expect($secondSourceValue->fresh()->getRawOriginal('updated_at'))
            ->toBe($originalSecondValue['updated_at']);

        expect($thirdSourceValue->fresh()->getRawOriginal('updated_at'))
            ->toBe($originalThirdValue['updated_at']);

        // Haal beide gekopieerde catalogusvermeldingen op.
        $firstTargetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $firstStamp->id)
            ->sole();

        $secondTargetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $secondStamp->id)
            ->sole();

        // Controleer bijzondere tekens en meerregelige notities.
        expect($firstTargetEntry->catalog_number)
            ->toBe('LW-005/Ä');

        expect($firstTargetEntry->notes)
            ->toBe("Nederland – België – Deutschland\nÉdition spéciale – España");

        // Controleer dat lege notities behouden blijven.
        expect($secondTargetEntry->catalog_number)->toBe('LW-005/B');
        expect($secondTargetEntry->notes)->toBeNull();

        // Haal de gekopieerde cataloguswaarden op.
        $firstTargetValue = $firstTargetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $secondTargetValue = $firstTargetEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        $thirdTargetValue = $secondTargetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer de eerste gekopieerde cataloguswaarde.
        expect($firstTargetValue->catalog_value)->toBe('175.95');
        expect($firstTargetValue->currency)->toBe('EUR');

        expect($firstTargetValue->notes)
            ->toBe("Postfris – Neuf sans charnière\nMNH – Postfrisch");

        expect($firstTargetValue->needs_review)->toBeTrue();

        // Controleer de tweede gekopieerde cataloguswaarde.
        expect($secondTargetValue->catalog_value)->toBe('0.00');
        expect($secondTargetValue->currency)->toBe('CHF');
        expect($secondTargetValue->notes)->toBeNull();
        expect($secondTargetValue->needs_review)->toBeTrue();

        // Controleer de derde gekopieerde cataloguswaarde.
        expect($thirdTargetValue->catalog_value)->toBe('89.50');
        expect($thirdTargetValue->currency)->toBe('USD');

        expect($thirdTargetValue->notes)
            ->toBe("Bijzondere waardering\nSpecial valuation");

        expect($thirdTargetValue->needs_review)->toBeTrue();

        // Controleer dat de nieuwe vermeldingen later zijn aangemaakt.
        expect($firstTargetEntry->created_at->greaterThan(
            $firstSourceEntry->fresh()->created_at
        ))->toBeTrue();

        expect($secondTargetEntry->created_at->greaterThan(
            $secondSourceEntry->fresh()->created_at
        ))->toBeTrue();

        // Controleer dat de nieuwe waarden later zijn aangemaakt.
        expect($firstTargetValue->created_at->greaterThan(
            $firstSourceValue->fresh()->created_at
        ))->toBeTrue();

        expect($secondTargetValue->created_at->greaterThan(
            $secondSourceValue->fresh()->created_at
        ))->toBeTrue();

        expect($thirdTargetValue->created_at->greaterThan(
            $thirdSourceValue->fresh()->created_at
        ))->toBeTrue();

        // Controleer de geschiedenisregistratie.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(5);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->catalog_edition_id)->toBe($targetEdition->id);
        expect($batch->operation)->toBe('duplicate');
        expect($batch->logs()->count())->toBe(5);

        // Controleer dat alle wijzigingslogs bij de doeleditie horen.
        expect(
            $batch->logs()
                ->where('catalog_edition_id', $targetEdition->id)
                ->where('action', 'created')
                ->count()
        )->toBe(5);

        // Controleer dat er geen geschiedenis voor de broneditie bestaat.
        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een directe aanroep van confirmSave zonder voorafgaande duplicatievoorbereiding wordt geweigerd', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-006',
        'notes' => 'Catalogusvermelding voor onbevestigde duplicatie.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '65.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor onbevestigde duplicatie.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met geldige gegevens.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-07-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie zonder voorafgaande bevestiging.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat de juiste broneditie is geselecteerd.
        $component
            ->assertSet('source_edition_id', $this->sourceEdition->id)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat er geen bevestigingshash aanwezig is.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Probeer de duplicatie rechtstreeks te bevestigen,
        // zonder eerst de methode save() aan te roepen.
        $component
            ->call('confirmSave')
            ->assertStatus(403);

        // Controleer dat er nog steeds geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe cataloguseditie is aangemaakt.
        expect(CatalogEdition::count())->toBe(1);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een duplicatiebevestiging na annulering wordt geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-007',
        'notes' => 'Catalogusvermelding voor bevestiging na annulering.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '75.50',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor bevestiging na annulering.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met geldige gegevens.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-08-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatiebevestiging na annulering.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat een geldige bevestigingshash aanwezig is.
        expect(session()->has($sessionKey))->toBeTrue();

        $confirmationHash = session()->get($sessionKey);

        expect($confirmationHash)->toBeString();
        expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

        // Controleer dat er vóór bevestiging niets is opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Annuleer de voorbereide duplicatie.
        $component
            ->call('cancelDuplication')
            ->assertHasNoErrors();

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat de formuliergegevens nog aanwezig zijn.
        $component
            ->assertSet('edition_name', 'Editie 2027')
            ->assertSet('edition_year', 2027)
            ->assertSet('source_edition_id', $this->sourceEdition->id)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat annulering geen gegevens heeft opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Probeer de geannuleerde duplicatie alsnog te bevestigen.
        $component
            ->call('confirmSave')
            ->assertStatus(403);

        // Controleer dat de bevestigingshash afwezig blijft.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe cataloguseditie is aangemaakt.
        expect(CatalogEdition::count())->toBe(1);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een duplicatiebevestiging wordt geweigerd wanneer formuliergegevens na voorbereiding zijn gewijzigd', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-008',
        'notes' => 'Catalogusvermelding voor gewijzigde duplicatiegegevens.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '85.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor gewijzigde duplicatiegegevens.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met geldige gegevens.
        // Het kopiëren van cataloguswaarden is aanvankelijk uitgeschakeld.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-09-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie met gewijzigde formuliergegevens.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', false);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();

        // Controleer de oorspronkelijke duplicatie-instellingen.
        $component
            ->assertSet('source_edition_id', $this->sourceEdition->id)
            ->assertSet('copy_catalog_values', false);

        // Bereid de duplicatie zonder cataloguswaarden voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat een geldige bevestigingshash aanwezig is.
        expect(session()->has($sessionKey))->toBeTrue();

        $originalConfirmationHash = session()->get($sessionKey);

        expect($originalConfirmationHash)->toBeString();
        expect($originalConfirmationHash)->toMatch('/^[a-f0-9]{64}$/');

        // Controleer dat er vóór bevestiging niets is opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Wijzig de duplicatie-instellingen na de voorbereiding.
        // Probeer nu alsnog de cataloguswaarden mee te kopiëren.
        $component
            ->set('copy_catalog_values', true)
            ->assertSet('copy_catalog_values', true)
            ->assertSet('source_edition_id', $this->sourceEdition->id);

        // Controleer dat de overige formuliergegevens behouden blijven.
        $component
            ->assertSet('edition_name', 'Editie 2027')
            ->assertSet('edition_year', 2027)
            ->assertSet('currency', 'EUR');

        // Controleer dat de oorspronkelijke bevestigingshash
        // niet automatisch is vervangen.
        expect(session()->has($sessionKey))->toBeTrue();

        expect(session()->get($sessionKey))
            ->toBe($originalConfirmationHash);

        // Controleer dat de wijziging nog niets heeft opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Probeer de gewijzigde duplicatie te bevestigen.
        // De bevestigingshash komt niet meer overeen.
        $component
            ->call('confirmSave')
            ->assertStatus(403);

        // Controleer dat de ongeldige bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe cataloguseditie is aangemaakt.
        expect(CatalogEdition::count())->toBe(1);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een eerder gebruikte duplicatiebevestiging kan niet opnieuw worden gebruikt', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-009',
        'notes' => 'Catalogusvermelding voor hergebruik van bevestiging.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '95.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor hergebruik van bevestiging.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier met geldige gegevens.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-10-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie met een eenmalige bevestiging.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat er nog geen bevestigingshash bestaat.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat een geldige bevestigingshash aanwezig is.
    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat er vóór bevestiging niets is opgeslagen.
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie voor de eerste keer.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verbruikt.
    expect(session()->has($sessionKey))->toBeFalse();
    expect(session()->get($sessionKey))->toBeNull();

    // Controleer dat de eerste duplicatie succesvol is uitgevoerd.
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(2);

    // Haal de succesvol aangemaakte doeleditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Haal de gekopieerde cataloguswaarde op.
    $targetValue = $targetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    // Controleer de gekopieerde gegevens.
    expect($targetEntry->catalog_number)->toBe('LW-009');
    expect($targetEntry->notes)->toBe($sourceEntry->notes);

    expect($targetValue->catalog_value)->toBe('95.75');
    expect($targetValue->currency)->toBe('EUR');
    expect($targetValue->notes)->toBe($sourceValue->notes);
    expect($targetValue->needs_review)->toBeTrue();

    // Haal de geschiedenisbatch van de eerste duplicatie op.
    $batch = CatalogChangeBatch::query()->sole();

    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->user_id)->toBe($this->admin->id);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->logs()->count())->toBe(2);

    // Bewaar volledige snapshots van de succesvol aangemaakte gegevens.
    $originalTargetEdition = $targetEdition->fresh()->getAttributes();
    $originalTargetEntry = $targetEntry->fresh()->getAttributes();
    $originalTargetValue = $targetValue->fresh()->getAttributes();
    $originalBatch = $batch->fresh()->getAttributes();

    $originalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // tijdens de tweede bevestigingspoging zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Probeer dezelfde duplicatie opnieuw te bevestigen,
        // zonder opnieuw de methode save() aan te roepen.
        $component
            ->call('confirmSave')
            ->assertStatus(403);

        // Controleer dat de bevestigingshash afwezig blijft.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen tweede cataloguseditie is aangemaakt.
        expect(CatalogEdition::count())->toBe(2);

        expect(
            $this->catalog->editions()
                ->where('edition_name', 'Editie 2027')
                ->where('edition_year', 2027)
                ->count()
        )->toBe(1);

        // Controleer dat er geen dubbele catalogusgegevens bestaan.
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);

        expect($targetEdition->stampEntries()->count())->toBe(1);
        expect($targetEdition->stampValues()->count())->toBe(1);

        // Controleer dat alle oorspronkelijke brongegevens
        // volledig ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de succesvol aangemaakte doeleditie
        // volledig ongewijzigd is.
        expect($targetEdition->fresh()->getAttributes())
            ->toEqual($originalTargetEdition);

        // Controleer dat de gekopieerde catalogusvermelding
        // volledig ongewijzigd is.
        expect($targetEntry->fresh()->getAttributes())
            ->toEqual($originalTargetEntry);

        // Controleer dat de gekopieerde cataloguswaarde
        // volledig ongewijzigd is.
        expect($targetValue->fresh()->getAttributes())
            ->toEqual($originalTargetValue);

        // Controleer beide controlemarkeringen.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();
        expect($targetValue->fresh()->needs_review)->toBeTrue();

        // Controleer dat er geen aanvullende geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(2);

        // Controleer dat de oorspronkelijke geschiedenisbatch
        // volledig ongewijzigd is.
        expect($batch->fresh()->getAttributes())
            ->toEqual($originalBatch);

        // Controleer dat beide oorspronkelijke wijzigingslogs
        // volledig ongewijzigd zijn.
        $currentLogs = $batch->logs()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        expect($currentLogs)->toEqual($originalLogs);

        // Controleer dat er uitsluitend aanmaaklogs bestaan.
        expect(
            CatalogChangeLog::query()
                ->where('action', 'created')
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een broneditie uit een andere catalogus wordt geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere catalogus Livewire Duplicatie',
        'abbreviation' => 'ALD',
    ]);

    // Koppel hetzelfde land aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie in de tweede catalogus aan.
    $otherSourceEdition = $otherCatalog->editions()->create([
        'edition_name' => 'Andere editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak een postzegel aan die in beide catalogi voorkomt.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de toegestane broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-010-A',
        'notes' => 'Catalogusvermelding in de toegestane broneditie.',
    ]);

    // Maak een cataloguswaarde in de toegestane broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '105.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde in de toegestane broneditie.',
        'needs_review' => false,
    ]);

    // Maak een catalogusvermelding in de andere catalogus aan.
    $otherSourceEntry = $otherSourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-010-B',
        'notes' => 'Catalogusvermelding in de andere catalogus.',
    ]);

    // Maak een cataloguswaarde in de andere catalogus aan.
    $otherSourceValue = $otherSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '205.50',
        'currency' => 'USD',
        'notes' => 'Cataloguswaarde in de andere catalogus.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van beide catalogi.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalOtherCatalog = $otherCatalog->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide bronedities.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalOtherSourceEdition = $otherSourceEdition->fresh()->getAttributes();

    // Bewaar een volledige snapshot van de postzegel.
    $originalStamp = $stamp->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide catalogusvermeldingen.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalOtherSourceEntry = $otherSourceEntry->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide cataloguswaarden.
    $originalSourceValue = $sourceValue->fresh()->getAttributes();
    $originalOtherSourceValue = $otherSourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(2);
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Controleer dat beide bronedities tot verschillende catalogi behoren.
    expect($this->sourceEdition->catalog_id)->toBe($this->catalog->id);
    expect($otherSourceEdition->catalog_id)->toBe($otherCatalog->id);
    expect($otherSourceEdition->catalog_id)->not->toBe($this->catalog->id);

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier voor de eerste catalogus.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-11-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie vanuit een niet-toegestane catalogus.')
            ->set('source_edition_id', $otherSourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat de niet-toegestane broneditie is ingesteld.
        $component
            ->assertSet('source_edition_id', $otherSourceEdition->id)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Probeer de duplicatie vanuit de andere catalogus voor te bereiden.
        $component
            ->call('save')
            ->assertHasErrors([
                'source_edition_id' => 'exists',
            ])
            ->assertHasNoErrors([
                'edition_name',
                'edition_year',
                'publication_date',
                'currency',
                'notes',
                'copy_catalog_values',
            ]);

        // Controleer dat er geen bevestigingshash is aangemaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe catalogus of editie is aangemaakt.
        expect(Catalog::count())->toBe(2);
        expect(CatalogEdition::count())->toBe(2);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $otherCatalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);

        // Controleer dat beide catalogi volledig ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($otherCatalog->fresh()->getAttributes())
            ->toEqual($originalOtherCatalog);

        // Controleer dat beide bronedities volledig ongewijzigd zijn.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($otherSourceEdition->fresh()->getAttributes())
            ->toEqual($originalOtherSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat beide catalogusvermeldingen ongewijzigd zijn.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($otherSourceEntry->fresh()->getAttributes())
            ->toEqual($originalOtherSourceEntry);

        // Controleer dat beide cataloguswaarden ongewijzigd zijn.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        expect($otherSourceValue->fresh()->getAttributes())
            ->toEqual($originalOtherSourceValue);

        // Controleer dat beide controlemarkeringen behouden blijven.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();
        expect($otherSourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een niet-bestaande broneditie wordt geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de bestaande broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-011',
        'notes' => 'Catalogusvermelding voor een niet-bestaande broneditie.',
    ]);

    // Maak een cataloguswaarde in de bestaande broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '115.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor een niet-bestaande broneditie.',
        'needs_review' => false,
    ]);

    // Bepaal een cataloguseditie-ID die niet bestaat.
    $nonExistingEditionId = CatalogEdition::query()->max('id') + 1000;

    // Controleer dat deze cataloguseditie daadwerkelijk niet bestaat.
    $this->assertDatabaseMissing('catalog_editions', [
        'id' => $nonExistingEditionId,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met geldige gegevens,
        // behalve het niet-bestaande broneditie-ID.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-12-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie vanuit een niet-bestaande broneditie.')
            ->set('source_edition_id', $nonExistingEditionId)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat het niet-bestaande broneditie-ID is ingesteld.
        $component
            ->assertSet('source_edition_id', $nonExistingEditionId)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Probeer de duplicatie vanuit de niet-bestaande
        // broneditie voor te bereiden.
        $component
            ->call('save')
            ->assertHasErrors([
                'source_edition_id' => 'exists',
            ])
            ->assertHasNoErrors([
                'edition_name',
                'edition_year',
                'publication_date',
                'currency',
                'notes',
                'copy_catalog_values',
            ]);

        // Controleer dat er geen bevestigingshash is aangemaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe catalogus of editie is aangemaakt.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(1);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
        ]);

        // Controleer dat de opgegeven broneditie nog steeds niet bestaat.
        $this->assertDatabaseMissing('catalog_editions', [
            'id' => $nonExistingEditionId,
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de bestaande broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de oorspronkelijke cataloguswaarde
        // volledig ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('ongeldige verplichte editiegegevens worden geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-012',
        'notes' => 'Catalogusvermelding voor ongeldige editiegegevens.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '125.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor ongeldige editiegegevens.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met een geldige
        // broneditie, maar zonder verplichte editiegegevens.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', '')
            ->set('edition_year', null)
            ->set('publication_date', '2027-01-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie met ongeldige editiegegevens.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer dat de broneditie correct is ingesteld.
        $component
            ->assertSet('source_edition_id', $this->sourceEdition->id)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Situatie A: beide verplichte editiegegevens ontbreken.
        $component
            ->call('save')
            ->assertHasErrors([
                'edition_name' => 'required',
                'edition_year' => 'required',
            ])
            ->assertHasNoErrors([
                'publication_date',
                'currency',
                'notes',
                'source_edition_id',
                'copy_catalog_values',
            ]);

        // Controleer dat de eerste validatiefout
        // geen gegevensopslag heeft veroorzaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Situatie B: een te lange editienaam en een te laag editiejaar.
        $component
            ->set('edition_name', str_repeat('A', 151))
            ->set('edition_year', 0)
            ->call('save')
            ->assertHasErrors([
                'edition_name' => 'max',
                'edition_year' => 'between',
            ])
            ->assertHasNoErrors([
                'publication_date',
                'currency',
                'notes',
                'source_edition_id',
                'copy_catalog_values',
            ]);

        // Controleer dat de tweede validatiefout
        // geen gegevensopslag heeft veroorzaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Situatie C: een geldige editienaam,
        // maar een editiejaar boven de toegestane bovengrens.
        $component
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 65536)
            ->call('save')
            ->assertHasErrors([
                'edition_year' => 'between',
            ])
            ->assertHasNoErrors([
                'edition_name',
                'publication_date',
                'currency',
                'notes',
                'source_edition_id',
                'copy_catalog_values',
            ]);

        // Controleer dat er ook na de derde validatiefout
        // geen bevestigingshash is aangemaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe catalogus of editie bestaat.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(1);

        $this->assertDatabaseMissing('catalog_editions', [
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de oorspronkelijke cataloguswaarde
        // volledig ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een dubbele combinatie van editienaam en editiejaar wordt geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een bestaande doeleditie aan.
    $existingTargetEdition = $this->catalog->editions()->create([
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'publication_date' => '2027-01-01',
        'currency' => 'USD',
        'notes' => 'Oorspronkelijke gegevens van de bestaande doeleditie.',
    ]);

    // Maak een postzegel aan die in beide edities voorkomt.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-013-A',
        'notes' => 'Oorspronkelijke catalogusvermelding in de broneditie.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '135.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde in de broneditie.',
        'needs_review' => false,
    ]);

    // Maak een afwijkende catalogusvermelding
    // in de bestaande doeleditie aan.
    $existingTargetEntry = $existingTargetEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-013-B',
        'notes' => 'Oorspronkelijke catalogusvermelding in de doeleditie.',
    ]);

    // Maak een afwijkende cataloguswaarde
    // in de bestaande doeleditie aan.
    $existingTargetValue = $existingTargetEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '275.50',
        'currency' => 'USD',
        'notes' => 'Oorspronkelijke cataloguswaarde in de doeleditie.',
        'needs_review' => true,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $existingTargetEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();

    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    $originalTargetEntry = $existingTargetEntry->fresh()->getAttributes();
    $originalTargetValue = $existingTargetValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Controleer dat beide edities tot dezelfde catalogus behoren.
    expect($this->sourceEdition->catalog_id)->toBe($this->catalog->id);
    expect($existingTargetEdition->catalog_id)->toBe($this->catalog->id);

    // Controleer dat de combinatie van editienaam
    // en editiejaar al bestaat.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $existingTargetEdition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier met een combinatie
        // van editienaam en editiejaar die al bestaat.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-02-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Poging om een bestaande editie opnieuw aan te maken.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Controleer de ingestelde formuliergegevens.
        $component
            ->assertSet('edition_name', 'Editie 2027')
            ->assertSet('edition_year', 2027)
            ->assertSet('source_edition_id', $this->sourceEdition->id)
            ->assertSet('copy_catalog_values', true);

        // Controleer dat er nog geen bevestigingshash bestaat.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Probeer de duplicatie voor te bereiden.
        $component
            ->call('save')
            ->assertHasErrors([
                'edition_name' => 'unique',
            ])
            ->assertHasNoErrors([
                'edition_year',
                'publication_date',
                'currency',
                'notes',
                'source_edition_id',
                'copy_catalog_values',
            ]);

        // Controleer dat er geen bevestigingshash is aangemaakt.
        expect(session()->has($sessionKey))->toBeFalse();
        expect(session()->get($sessionKey))->toBeNull();

        // Controleer dat er geen nieuwe catalogus of editie bestaat.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(2);

        // Controleer dat de combinatie van editienaam
        // en editiejaar nog steeds slechts eenmaal voorkomt.
        expect(
            $this->catalog->editions()
                ->where('edition_name', 'Editie 2027')
                ->where('edition_year', 2027)
                ->count()
        )->toBe(1);

        // Controleer dat de oorspronkelijke doeleditie behouden is.
        $this->assertDatabaseHas('catalog_editions', [
            'id' => $existingTargetEdition->id,
            'catalog_id' => $this->catalog->id,
            'edition_name' => 'Editie 2027',
            'edition_year' => 2027,
            'currency' => 'USD',
            'notes' => 'Oorspronkelijke gegevens van de bestaande doeleditie.',
        ]);

        // Controleer dat er geen catalogusgegevens zijn gekopieerd.
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);

        // Controleer dat beide edities hun eigen gegevens behouden.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1);
        expect($this->sourceEdition->stampValues()->count())->toBe(1);

        expect($existingTargetEdition->stampEntries()->count())->toBe(1);
        expect($existingTargetEdition->stampValues()->count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de bestaande doeleditie volledig ongewijzigd is.
        expect($existingTargetEdition->fresh()->getAttributes())
            ->toEqual($originalTargetEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de catalogusvermelding
        // in de broneditie ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de cataloguswaarde
        // in de broneditie ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de catalogusvermelding
        // in de bestaande doeleditie ongewijzigd is.
        expect($existingTargetEntry->fresh()->getAttributes())
            ->toEqual($originalTargetEntry);

        // Controleer dat de cataloguswaarde
        // in de bestaande doeleditie ongewijzigd is.
        expect($existingTargetValue->fresh()->getAttributes())
            ->toEqual($originalTargetValue);

        // Controleer dat beide controlemarkeringen behouden blijven.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();
        expect($existingTargetValue->fresh()->needs_review)->toBeTrue();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de ingelogde administrator wordt correct geregistreerd in de geschiedenis van een duplicatie vanuit Livewire', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-014',
        'notes' => 'Catalogusvermelding voor registratie van de administrator.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '145.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor registratie van de administrator.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar de oorspronkelijke gebruikersnaam van de administrator.
    $originalAdminName = $this->admin->name;

    // Controleer de uitgangssituatie.
    expect(auth()->id())->toBe($this->admin->id);
    expect($this->admin->is_admin)->toBeTrue();
    expect($this->user->is_admin)->toBeFalse();

    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-03-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie uitgevoerd door de administrator.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat er nog geen geschiedenis is geregistreerd.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(CatalogEdition::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat de duplicatie succesvol is uitgevoerd.
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(2);

    // Haal de nieuw aangemaakte doeleditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Haal de gekopieerde cataloguswaarde op.
    $targetValue = $targetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    // Controleer de gekopieerde catalogusgegevens.
    expect($targetEntry->catalog_number)->toBe('LW-014');
    expect($targetValue->catalog_value)->toBe('145.75');
    expect($targetValue->currency)->toBe('EUR');
    expect($targetValue->needs_review)->toBeTrue();

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de koppeling met de administrator.
    expect($batch->user_id)->toBe($this->admin->id);
    expect($batch->user_id)->not->toBe($this->user->id);

    // Controleer de opgeslagen gebruikersnaam.
    expect($batch->user_name)->toBe($originalAdminName);
    expect($batch->user_name)->not->toBe($this->user->name);

    // Controleer de Eloquent-relatie met de administrator.
    expect($batch->user)->toBeInstanceOf(User::class);
    expect($batch->user->id)->toBe($this->admin->id);
    expect($batch->user->name)->toBe($originalAdminName);
    expect($batch->user->is_admin)->toBeTrue();

    // Controleer de overige gegevens van de geschiedenisbatch.
    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->catalog_name)->toBe($this->catalog->name);
    expect($batch->catalog_edition_name)->toBe('Editie 2027');
    expect($batch->logs()->count())->toBe(2);

    // Controleer de opgeslagen gegevens rechtstreeks in de database.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $targetEdition->id,
        'user_id' => $this->admin->id,
        'user_name' => $originalAdminName,
        'operation' => 'duplicate',
    ]);

    // Controleer dat er geen geschiedenisbatch
    // aan de gewone gebruiker is gekoppeld.
    expect(
        CatalogChangeBatch::query()
            ->where('user_id', $this->user->id)
            ->count()
    )->toBe(0);

    // Controleer dat er geen anonieme geschiedenisbatch bestaat.
    expect(
        CatalogChangeBatch::query()
            ->whereNull('user_id')
            ->count()
    )->toBe(0);

    // Controleer beide wijzigingslogs.
    $logs = $batch->logs()->orderBy('id')->get();

    foreach ($logs as $log) {
        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($targetEdition->id);
        expect($log->stamp_id)->toBe($stamp->id);
        expect($log->action)->toBe('created');
        expect($log->old_values)->toBeNull();
        expect($log->batch->user_id)->toBe($this->admin->id);
        expect($log->batch->user_name)->toBe($originalAdminName);
    }

    // Controleer dat beide gebruikers tijdens de duplicatie
    // volledig ongewijzigd zijn gebleven.
    expect($this->admin->fresh()->getAttributes())
        ->toEqual($originalAdmin);

    expect($this->user->fresh()->getAttributes())
        ->toEqual($originalUser);

    // Controleer dat alle oorspronkelijke brongegevens
    // volledig ongewijzigd zijn gebleven.
    expect($this->catalog->fresh()->getAttributes())
        ->toEqual($originalCatalog);

    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($stamp->fresh()->getAttributes())
        ->toEqual($originalStamp);

    expect($sourceEntry->fresh()->getAttributes())
        ->toEqual($originalSourceEntry);

    expect($sourceValue->fresh()->getAttributes())
        ->toEqual($originalSourceValue);

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Bewaar snapshots van de aangemaakte doelgegevens en geschiedenis.
    $originalTargetEdition = $targetEdition->fresh()->getAttributes();
    $originalTargetEntry = $targetEntry->fresh()->getAttributes();
    $originalTargetValue = $targetValue->fresh()->getAttributes();
    $originalBatch = $batch->fresh()->getAttributes();

    $originalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Verplaats de testtijd om een latere naamswijziging te simuleren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de naam van de administrator na de duplicatie.
        $newAdminName = 'Administrator met gewijzigde naam';

        $this->admin->update([
            'name' => $newAdminName,
        ]);

        // Controleer dat de actuele gebruikersnaam is gewijzigd.
        expect($this->admin->fresh()->name)->toBe($newAdminName);
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        // Haal de geschiedenisbatch opnieuw uit de database op.
        $batch->refresh();

        // Controleer dat de gebruikerskoppeling behouden blijft.
        expect($batch->user_id)->toBe($this->admin->id);

        // Controleer dat de relatie de actuele gebruikersnaam bevat.
        expect($batch->user()->firstOrFail()->name)
            ->toBe($newAdminName);

        // Controleer dat de historische gebruikersnaam behouden blijft.
        expect($batch->user_name)->toBe($originalAdminName);
        expect($batch->user_name)->not->toBe($newAdminName);

        // Controleer dat de geschiedenisbatch volledig ongewijzigd is.
        expect($batch->getAttributes())->toEqual($originalBatch);

        // Controleer dat beide wijzigingslogs ongewijzigd zijn.
        $currentLogs = $batch->logs()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        expect($currentLogs)->toEqual($originalLogs);

        // Controleer dat de gekopieerde gegevens ongewijzigd zijn.
        expect($targetEdition->fresh()->getAttributes())
            ->toEqual($originalTargetEdition);

        expect($targetEntry->fresh()->getAttributes())
            ->toEqual($originalTargetEntry);

        expect($targetValue->fresh()->getAttributes())
            ->toEqual($originalTargetValue);

        // Controleer dat de gewone gebruiker ongewijzigd is gebleven.
        expect($this->user->fresh()->getAttributes())
            ->toEqual($originalUser);

        // Controleer dat de naamswijziging geen aanvullende
        // catalogusgeschiedenis heeft veroorzaakt.
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(2);

        // Controleer dat er geen actieve geschiedenisbatch bestaat.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de ingelogde gewone gebruiker wordt correct geregistreerd in de geschiedenis van een duplicatie vanuit Livewire', function () {
    $this->actingAs($this->user);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-015',
        'notes' => 'Catalogusvermelding voor registratie van een gewone gebruiker.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '155.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor registratie van een gewone gebruiker.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar de oorspronkelijke naam van de gewone gebruiker.
    $originalUserName = $this->user->name;

    // Controleer de uitgangssituatie.
    expect(auth()->id())->toBe($this->user->id);
    expect($this->user->is_admin)->toBeFalse();
    expect($this->admin->is_admin)->toBeTrue();

    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-04-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie uitgevoerd door een gewone gebruiker.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat er nog geen geschiedenis is geregistreerd.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(CatalogEdition::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie als gewone gebruiker.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat de duplicatie succesvol is uitgevoerd.
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(2);

    // Haal de nieuw aangemaakte doeleditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Haal de gekopieerde cataloguswaarde op.
    $targetValue = $targetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    // Controleer de gekopieerde catalogusgegevens.
    expect($targetEntry->catalog_number)->toBe('LW-015');
    expect($targetValue->catalog_value)->toBe('155.75');
    expect($targetValue->currency)->toBe('EUR');
    expect($targetValue->needs_review)->toBeTrue();

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de koppeling met de gewone gebruiker.
    expect($batch->user_id)->toBe($this->user->id);
    expect($batch->user_id)->not->toBe($this->admin->id);

    // Controleer de opgeslagen gebruikersnaam.
    expect($batch->user_name)->toBe($originalUserName);
    expect($batch->user_name)->not->toBe($this->admin->name);

    // Controleer de Eloquent-relatie met de gewone gebruiker.
    expect($batch->user)->toBeInstanceOf(User::class);
    expect($batch->user->id)->toBe($this->user->id);
    expect($batch->user->name)->toBe($originalUserName);
    expect($batch->user->is_admin)->toBeFalse();

    // Controleer de overige gegevens van de geschiedenisbatch.
    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->catalog_name)->toBe($this->catalog->name);
    expect($batch->catalog_edition_name)->toBe('Editie 2027');
    expect($batch->logs()->count())->toBe(2);

    // Controleer de opgeslagen gegevens rechtstreeks in de database.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $targetEdition->id,
        'user_id' => $this->user->id,
        'user_name' => $originalUserName,
        'operation' => 'duplicate',
    ]);

    // Controleer dat er geen geschiedenisbatch
    // aan de administrator is gekoppeld.
    expect(
        CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->count()
    )->toBe(0);

    // Controleer dat er geen anonieme geschiedenisbatch bestaat.
    expect(
        CatalogChangeBatch::query()
            ->whereNull('user_id')
            ->count()
    )->toBe(0);

    // Controleer beide wijzigingslogs.
    $logs = $batch->logs()->orderBy('id')->get();

    foreach ($logs as $log) {
        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($targetEdition->id);
        expect($log->stamp_id)->toBe($stamp->id);
        expect($log->action)->toBe('created');
        expect($log->old_values)->toBeNull();
        expect($log->batch->user_id)->toBe($this->user->id);
        expect($log->batch->user_name)->toBe($originalUserName);
    }

    // Controleer dat beide gebruikers tijdens de duplicatie
    // volledig ongewijzigd zijn gebleven.
    expect($this->admin->fresh()->getAttributes())
        ->toEqual($originalAdmin);

    expect($this->user->fresh()->getAttributes())
        ->toEqual($originalUser);

    // Controleer dat alle oorspronkelijke brongegevens
    // volledig ongewijzigd zijn gebleven.
    expect($this->catalog->fresh()->getAttributes())
        ->toEqual($originalCatalog);

    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($stamp->fresh()->getAttributes())
        ->toEqual($originalStamp);

    expect($sourceEntry->fresh()->getAttributes())
        ->toEqual($originalSourceEntry);

    expect($sourceValue->fresh()->getAttributes())
        ->toEqual($originalSourceValue);

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Bewaar snapshots van de aangemaakte doelgegevens en geschiedenis.
    $originalTargetEdition = $targetEdition->fresh()->getAttributes();
    $originalTargetEntry = $targetEntry->fresh()->getAttributes();
    $originalTargetValue = $targetValue->fresh()->getAttributes();
    $originalBatch = $batch->fresh()->getAttributes();

    $originalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Verplaats de testtijd om een latere naamswijziging te simuleren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de naam van de gewone gebruiker na de duplicatie.
        $newUserName = 'Gewone gebruiker met gewijzigde naam';

        $this->user->update([
            'name' => $newUserName,
        ]);

        // Controleer dat de actuele gebruikersnaam is gewijzigd.
        expect($this->user->fresh()->name)->toBe($newUserName);
        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Haal de geschiedenisbatch opnieuw uit de database op.
        $batch->refresh();

        // Controleer dat de gebruikerskoppeling behouden blijft.
        expect($batch->user_id)->toBe($this->user->id);

        // Controleer dat de relatie de actuele gebruikersnaam bevat.
        expect($batch->user()->firstOrFail()->name)
            ->toBe($newUserName);

        // Controleer dat de historische gebruikersnaam behouden blijft.
        expect($batch->user_name)->toBe($originalUserName);
        expect($batch->user_name)->not->toBe($newUserName);

        // Controleer dat de geschiedenisbatch volledig ongewijzigd is.
        expect($batch->getAttributes())->toEqual($originalBatch);

        // Controleer dat beide wijzigingslogs ongewijzigd zijn.
        $currentLogs = $batch->logs()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        expect($currentLogs)->toEqual($originalLogs);

        // Controleer dat de gekopieerde gegevens ongewijzigd zijn.
        expect($targetEdition->fresh()->getAttributes())
            ->toEqual($originalTargetEdition);

        expect($targetEntry->fresh()->getAttributes())
            ->toEqual($originalTargetEntry);

        expect($targetValue->fresh()->getAttributes())
            ->toEqual($originalTargetValue);

        // Controleer dat de administrator ongewijzigd is gebleven.
        expect($this->admin->fresh()->getAttributes())
            ->toEqual($originalAdmin);

        // Controleer dat de naamswijziging geen aanvullende
        // catalogusgeschiedenis heeft veroorzaakt.
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(2);

        // Controleer dat er geen actieve geschiedenisbatch bestaat.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de geschiedenis van een duplicatie vanuit Livewire wordt uitsluitend aan de juiste doeleditie gekoppeld', function () {
    $this->actingAs($this->admin);

    // Maak een andere editie binnen dezelfde catalogus aan.
    $otherEdition = $this->catalog->editions()->create([
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
        'notes' => 'Andere editie binnen dezelfde catalogus.',
    ]);

    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere catalogus voor geschiedenisregistratie',
        'abbreviation' => 'ACG',
    ]);

    // Koppel hetzelfde land aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een editie binnen de tweede catalogus aan.
    $otherCatalogEdition = $otherCatalog->editions()->create([
        'edition_name' => 'Andere editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
        'notes' => 'Editie binnen een andere catalogus.',
    ]);

    // Maak een postzegel aan die in alle drie de edities voorkomt.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-016-A',
        'notes' => 'Catalogusvermelding in de broneditie.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '165.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde in de broneditie.',
        'needs_review' => false,
    ]);

    // Maak een catalogusvermelding in de andere editie
    // binnen dezelfde catalogus aan.
    $otherEntry = $otherEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-016-B',
        'notes' => 'Catalogusvermelding in een andere editie.',
    ]);

    // Maak een cataloguswaarde in deze andere editie aan.
    $otherValue = $otherEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '265.50',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde in een andere editie.',
        'needs_review' => true,
    ]);

    // Maak een catalogusvermelding in de andere catalogus aan.
    $otherCatalogEntry = $otherCatalogEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-016-C',
        'notes' => 'Catalogusvermelding in een andere catalogus.',
    ]);

    // Maak een cataloguswaarde in de andere catalogus aan.
    $otherCatalogValue = $otherCatalogEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '365.25',
        'currency' => 'USD',
        'notes' => 'Cataloguswaarde in een andere catalogus.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van beide catalogi.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalOtherCatalog = $otherCatalog->fresh()->getAttributes();

    // Bewaar volledige snapshots van de drie bestaande edities.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalOtherEdition = $otherEdition->fresh()->getAttributes();
    $originalOtherCatalogEdition = $otherCatalogEdition->fresh()->getAttributes();

    // Bewaar een volledige snapshot van de postzegel.
    $originalStamp = $stamp->fresh()->getAttributes();

    // Bewaar volledige snapshots van de catalogusvermeldingen.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalOtherEntry = $otherEntry->fresh()->getAttributes();
    $originalOtherCatalogEntry = $otherCatalogEntry->fresh()->getAttributes();

    // Bewaar volledige snapshots van de cataloguswaarden.
    $originalSourceValue = $sourceValue->fresh()->getAttributes();
    $originalOtherValue = $otherValue->fresh()->getAttributes();
    $originalOtherCatalogValue = $otherCatalogValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(2);
    expect(CatalogEdition::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(3);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-05-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie met geschiedenis voor de juiste doeleditie.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat vóór bevestiging niets is opgeslagen.
        expect(session()->has($sessionKey))->toBeTrue();
        expect(CatalogEdition::count())->toBe(3);
        expect(StampCatalogEntry::count())->toBe(3);
        expect(StampCatalogValue::count())->toBe(3);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Bevestig de duplicatie.
        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('backend.catalogs.editions.index', $this->catalog)
            );

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();

        // Controleer de nieuwe databasesituatie.
        expect(Catalog::count())->toBe(2);
        expect(CatalogEdition::count())->toBe(4);
        expect(StampCatalogEntry::count())->toBe(4);
        expect(StampCatalogValue::count())->toBe(4);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(2);

        // Haal de nieuw aangemaakte doeleditie op.
        $targetEdition = $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->sole();

        // Controleer dat de doeleditie tot de juiste catalogus behoort.
        expect($targetEdition->catalog_id)->toBe($this->catalog->id);
        expect($targetEdition->catalog_id)->not->toBe($otherCatalog->id);

        // Controleer dat de doeleditie verschilt van alle bestaande edities.
        expect($targetEdition->id)->not->toBe($this->sourceEdition->id);
        expect($targetEdition->id)->not->toBe($otherEdition->id);
        expect($targetEdition->id)->not->toBe($otherCatalogEdition->id);

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Haal de gekopieerde cataloguswaarde op.
        $targetValue = $targetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer dat uitsluitend gegevens uit de broneditie
        // naar de doeleditie zijn gekopieerd.
        expect($targetEntry->catalog_number)->toBe('LW-016-A');
        expect($targetEntry->notes)->toBe($sourceEntry->notes);

        expect($targetValue->catalog_value)->toBe('165.75');
        expect($targetValue->currency)->toBe('EUR');
        expect($targetValue->notes)->toBe($sourceValue->notes);
        expect($targetValue->needs_review)->toBeTrue();

        // Haal de geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer dat de batch uitsluitend aan de doeleditie is gekoppeld.
        expect($batch->catalog_edition_id)->toBe($targetEdition->id);

        expect($batch->catalog_edition_id)
            ->not->toBe($this->sourceEdition->id);

        expect($batch->catalog_edition_id)
            ->not->toBe($otherEdition->id);

        expect($batch->catalog_edition_id)
            ->not->toBe($otherCatalogEdition->id);

        // Controleer de Eloquent-relatie met de doeleditie.
        expect($batch->catalogEdition)->toBeInstanceOf(CatalogEdition::class);
        expect($batch->catalogEdition->id)->toBe($targetEdition->id);
        expect($batch->catalogEdition->catalog_id)->toBe($this->catalog->id);

        // Controleer de overige batchgegevens.
        expect($batch->operation)->toBe('duplicate');
        expect($batch->user_id)->toBe($this->admin->id);
        expect($batch->catalog_name)->toBe($this->catalog->name);
        expect($batch->catalog_edition_name)->toBe('Editie 2027');

        expect($batch->description)
            ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026');

        expect($batch->logs()->count())->toBe(2);

        // Haal het wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        // Controleer de koppeling met de nieuwe catalogusvermelding.
        expect($entryLog->catalog_change_batch_id)->toBe($batch->id);
        expect($entryLog->catalog_edition_id)->toBe($targetEdition->id);
        expect($entryLog->record_id)->toBe($targetEntry->id);
        expect($entryLog->record_id)->not->toBe($sourceEntry->id);
        expect($entryLog->stamp_id)->toBe($stamp->id);
        expect($entryLog->condition_id)->toBeNull();
        expect($entryLog->action)->toBe('created');
        expect($entryLog->old_values)->toBeNull();

        expect($entryLog->new_values)
            ->toEqual($targetEntry->fresh()->getAttributes());

        // Controleer de Eloquent-relatie met de doeleditie.
        expect($entryLog->catalogEdition->id)->toBe($targetEdition->id);

        // Haal het wijzigingslog van de cataloguswaarde op.
        $valueLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->sole();

        // Controleer de koppeling met de nieuwe cataloguswaarde.
        expect($valueLog->catalog_change_batch_id)->toBe($batch->id);
        expect($valueLog->catalog_edition_id)->toBe($targetEdition->id);
        expect($valueLog->record_id)->toBe($targetValue->id);
        expect($valueLog->record_id)->not->toBe($sourceValue->id);
        expect($valueLog->stamp_id)->toBe($stamp->id);
        expect($valueLog->condition_id)->toBe($this->postfris->id);
        expect($valueLog->action)->toBe('created');
        expect($valueLog->old_values)->toBeNull();

        expect($valueLog->new_values)
            ->toEqual($targetValue->fresh()->getAttributes());

        // Controleer de Eloquent-relatie met de doeleditie.
        expect($valueLog->catalogEdition->id)->toBe($targetEdition->id);

        // Controleer dat alle geschiedenis aan de doeleditie is gekoppeld.
        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $targetEdition->id)
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $targetEdition->id)
                ->count()
        )->toBe(2);

        // Controleer dat geen van de bestaande edities
        // een geschiedenisbatch of wijzigingslog heeft gekregen.
        foreach ([
            $this->sourceEdition,
            $otherEdition,
            $otherCatalogEdition,
        ] as $existingEdition) {
            expect(
                CatalogChangeBatch::query()
                    ->where('catalog_edition_id', $existingEdition->id)
                    ->count()
            )->toBe(0);

            expect(
                CatalogChangeLog::query()
                    ->where('catalog_edition_id', $existingEdition->id)
                    ->count()
            )->toBe(0);

            expect($existingEdition->stampEntries()->count())->toBe(1);
            expect($existingEdition->stampValues()->count())->toBe(1);
        }

        // Controleer dat beide catalogi volledig ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        expect($otherCatalog->fresh()->getAttributes())
            ->toEqual($originalOtherCatalog);

        // Controleer dat alle bestaande edities ongewijzigd zijn.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        expect($otherEdition->fresh()->getAttributes())
            ->toEqual($originalOtherEdition);

        expect($otherCatalogEdition->fresh()->getAttributes())
            ->toEqual($originalOtherCatalogEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat alle bestaande catalogusvermeldingen
        // volledig ongewijzigd zijn.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($otherEntry->fresh()->getAttributes())
            ->toEqual($originalOtherEntry);

        expect($otherCatalogEntry->fresh()->getAttributes())
            ->toEqual($originalOtherCatalogEntry);

        // Controleer dat alle bestaande cataloguswaarden
        // volledig ongewijzigd zijn.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        expect($otherValue->fresh()->getAttributes())
            ->toEqual($originalOtherValue);

        expect($otherCatalogValue->fresh()->getAttributes())
            ->toEqual($originalOtherCatalogValue);

        // Controleer alle controlemarkeringen.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();
        expect($otherValue->fresh()->needs_review)->toBeTrue();
        expect($otherCatalogValue->fresh()->needs_review)->toBeFalse();
        expect($targetValue->fresh()->needs_review)->toBeTrue();

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('historische catalogusnamen en editienamen blijven behouden na latere naamswijzigingen bij een duplicatie vanuit Livewire', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-017',
        'notes' => 'Catalogusvermelding voor historische naamsnapshots.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '175.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor historische naamsnapshots.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke catalogus- en editienamen.
    $originalCatalogName = $this->catalog->name;
    $originalSourceEditionName = $this->sourceEdition->edition_name;
    $originalTargetEditionName = 'Editie 2027';

    // Bepaal de verwachte historische beschrijving.
    $originalDescription =
        'Duplicatie van catalogusvermeldingen vanuit '
        .$originalSourceEditionName;

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer het duplicatieformulier.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', $originalTargetEditionName)
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-06-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Duplicatie voor behoud van historische namen.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Bepaal de sessiesleutel van deze component.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Bereid de duplicatie voor.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat vóór bevestiging niets is opgeslagen.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Bevestig de duplicatie.
    $component
        ->call('confirmSave')
        ->assertHasNoErrors()
        ->assertRedirect(
            route('backend.catalogs.editions.index', $this->catalog)
        );

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer de nieuwe databasesituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(2);

    // Haal de nieuw aangemaakte doeleditie op.
    $targetEdition = $this->catalog->editions()
        ->where('edition_name', $originalTargetEditionName)
        ->where('edition_year', 2027)
        ->sole();

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Haal de gekopieerde cataloguswaarde op.
    $targetValue = $targetEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    // Controleer de gekopieerde catalogusgegevens.
    expect($targetEntry->catalog_number)->toBe('LW-017');
    expect($targetValue->catalog_value)->toBe('175.75');
    expect($targetValue->currency)->toBe('EUR');
    expect($targetValue->needs_review)->toBeTrue();

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de oorspronkelijke historische namen.
    expect($batch->catalog_name)->toBe($originalCatalogName);
    expect($batch->catalog_edition_name)->toBe($originalTargetEditionName);
    expect($batch->description)->toBe($originalDescription);

    // Controleer de overige batchgegevens.
    expect($batch->catalog_edition_id)->toBe($targetEdition->id);
    expect($batch->user_id)->toBe($this->admin->id);
    expect($batch->user_name)->toBe($this->admin->name);
    expect($batch->operation)->toBe('duplicate');
    expect($batch->logs()->count())->toBe(2);

    // Controleer de historische gegevens rechtstreeks in de database.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $targetEdition->id,
        'catalog_name' => $originalCatalogName,
        'catalog_edition_name' => $originalTargetEditionName,
        'description' => $originalDescription,
        'operation' => 'duplicate',
    ]);

    // Controleer dat de actuele namen overeenkomen
    // met de historische namen direct na de duplicatie.
    expect($batch->catalogEdition->edition_name)
        ->toBe($originalTargetEditionName);

    expect($batch->catalogEdition->catalog->name)
        ->toBe($originalCatalogName);

    // Controleer dat de oorspronkelijke gegevens
    // tijdens de duplicatie volledig ongewijzigd zijn gebleven.
    expect($this->catalog->fresh()->getAttributes())
        ->toEqual($originalCatalog);

    expect($this->sourceEdition->fresh()->getAttributes())
        ->toEqual($originalSourceEdition);

    expect($stamp->fresh()->getAttributes())
        ->toEqual($originalStamp);

    expect($sourceEntry->fresh()->getAttributes())
        ->toEqual($originalSourceEntry);

    expect($sourceValue->fresh()->getAttributes())
        ->toEqual($originalSourceValue);

    // Bewaar volledige snapshots van de doelgegevens.
    $originalTargetEdition = $targetEdition->fresh()->getAttributes();
    $originalTargetEntry = $targetEntry->fresh()->getAttributes();
    $originalTargetValue = $targetValue->fresh()->getAttributes();

    // Bewaar een volledige snapshot van de geschiedenisbatch.
    $originalBatch = $batch->fresh()->getAttributes();

    // Bewaar volledige snapshots van alle wijzigingslogs.
    $originalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Controleer dat er geen actieve geschiedenisbatch achterblijft.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Verplaats de testtijd om latere naamswijzigingen te simuleren.
    $this->travel(1)->minutes();

    try {
        // Bepaal nieuwe namen voor de catalogus en beide edities.
        $newCatalogName = 'Catalogus met gewijzigde naam';
        $newSourceEditionName = 'Broneditie met gewijzigde naam';
        $newTargetEditionName = 'Doeleditie met gewijzigde naam';

        // Wijzig de naam van de catalogus.
        $this->catalog->update([
            'name' => $newCatalogName,
        ]);

        // Wijzig de naam van de broneditie.
        $this->sourceEdition->update([
            'edition_name' => $newSourceEditionName,
        ]);

        // Wijzig de naam van de doeleditie.
        $targetEdition->update([
            'edition_name' => $newTargetEditionName,
        ]);

        // Controleer dat alle actuele namen daadwerkelijk zijn gewijzigd.
        expect($this->catalog->fresh()->name)
            ->toBe($newCatalogName);

        expect($this->sourceEdition->fresh()->edition_name)
            ->toBe($newSourceEditionName);

        expect($targetEdition->fresh()->edition_name)
            ->toBe($newTargetEditionName);

        // Haal de geschiedenisbatch opnieuw uit de database op.
        $batch->refresh();

        // Controleer dat de koppeling met de doeleditie behouden blijft.
        expect($batch->catalog_edition_id)->toBe($targetEdition->id);

        // Controleer dat de Eloquent-relaties de actuele namen bevatten.
        expect($batch->catalogEdition->edition_name)
            ->toBe($newTargetEditionName);

        expect($batch->catalogEdition->catalog->name)
            ->toBe($newCatalogName);

        // Controleer dat de historische catalogusnaam behouden blijft.
        expect($batch->catalog_name)->toBe($originalCatalogName);
        expect($batch->catalog_name)->not->toBe($newCatalogName);

        // Controleer dat de historische naam van de doeleditie
        // behouden blijft.
        expect($batch->catalog_edition_name)
            ->toBe($originalTargetEditionName);

        expect($batch->catalog_edition_name)
            ->not->toBe($newTargetEditionName);

        // Controleer dat de historische beschrijving
        // de oorspronkelijke broneditienaam blijft bevatten.
        expect($batch->description)->toBe($originalDescription);

        expect($batch->description)
            ->toContain($originalSourceEditionName);

        expect($batch->description)
            ->not->toContain($newSourceEditionName);

        // Controleer dat de volledige geschiedenisbatch ongewijzigd is.
        expect($batch->getAttributes())->toEqual($originalBatch);

        // Controleer dat alle wijzigingslogs ongewijzigd zijn.
        $currentLogs = $batch->logs()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        expect($currentLogs)->toEqual($originalLogs);

        // Controleer de historische gegevens opnieuw
        // rechtstreeks in de database.
        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $targetEdition->id,
            'catalog_name' => $originalCatalogName,
            'catalog_edition_name' => $originalTargetEditionName,
            'description' => $originalDescription,
        ]);

        // Controleer dat alleen de naam en het wijzigingstijdstip
        // van de catalogus zijn gewijzigd.
        $expectedCatalog = $originalCatalog;
        $expectedCatalog['name'] = $newCatalogName;
        $expectedCatalog['updated_at'] =
            $this->catalog->fresh()->getRawOriginal('updated_at');

        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($expectedCatalog);

        // Controleer dat alleen de naam en het wijzigingstijdstip
        // van de broneditie zijn gewijzigd.
        $expectedSourceEdition = $originalSourceEdition;
        $expectedSourceEdition['edition_name'] = $newSourceEditionName;
        $expectedSourceEdition['updated_at'] =
            $this->sourceEdition->fresh()->getRawOriginal('updated_at');

        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($expectedSourceEdition);

        // Controleer dat alleen de naam en het wijzigingstijdstip
        // van de doeleditie zijn gewijzigd.
        $expectedTargetEdition = $originalTargetEdition;
        $expectedTargetEdition['edition_name'] = $newTargetEditionName;
        $expectedTargetEdition['updated_at'] =
            $targetEdition->fresh()->getRawOriginal('updated_at');

        expect($targetEdition->fresh()->getAttributes())
            ->toEqual($expectedTargetEdition);

        // Controleer dat de wijzigingstijdstippen daadwerkelijk
        // later zijn dan de oorspronkelijke tijdstempels.
        expect($this->catalog->fresh()->getRawOriginal('updated_at'))
            ->not->toBe($originalCatalog['updated_at']);

        expect($this->sourceEdition->fresh()->getRawOriginal('updated_at'))
            ->not->toBe($originalSourceEdition['updated_at']);

        expect($targetEdition->fresh()->getRawOriginal('updated_at'))
            ->not->toBe($originalTargetEdition['updated_at']);

        // Controleer dat de postzegel ongewijzigd is gebleven.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke brongegevens
        // volledig ongewijzigd zijn gebleven.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de gekopieerde doelgegevens
        // volledig ongewijzigd zijn gebleven.
        expect($targetEntry->fresh()->getAttributes())
            ->toEqual($originalTargetEntry);

        expect($targetValue->fresh()->getAttributes())
            ->toEqual($originalTargetValue);

        // Controleer dat beide controlemarkeringen behouden blijven.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();
        expect($targetValue->fresh()->needs_review)->toBeTrue();

        // Controleer dat de naamswijzigingen geen aanvullende
        // catalogusgeschiedenis hebben veroorzaakt.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(2);

        // Controleer dat er geen actieve geschiedenisbatch bestaat.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een duplicatie van meerdere catalogusvermeldingen en cataloguswaarden vanuit Livewire resulteert in een geschiedenisbatch met alle wijzigingslogs', function () {
    $this->actingAs($this->admin);

    // Maak vier postzegels voor de broneditie aan.
    $stampA = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2022,
    ]);

    $stampB = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    $stampC = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampD = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak vier catalogusvermeldingen in de broneditie aan.
    $sourceEntryA = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampA->id,
        'catalog_number' => 'LW-018-A',
        'notes' => 'Catalogusvermelding met twee cataloguswaarden.',
    ]);

    $sourceEntryB = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampB->id,
        'catalog_number' => 'LW-018-B',
        'notes' => 'Catalogusvermelding met een nulwaarde.',
    ]);

    $sourceEntryC = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampC->id,
        'catalog_number' => 'LW-018-C',
        'notes' => 'Catalogusvermelding met één cataloguswaarde.',
    ]);

    $sourceEntryD = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampD->id,
        'catalog_number' => 'LW-018-D',
        'notes' => null,
    ]);

    // Maak twee cataloguswaarden voor postzegel A aan.
    $sourceValueA1 = $sourceEntryA->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '185.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse cataloguswaarde van postzegel A.',
        'needs_review' => false,
    ]);

    $sourceValueA2 = $sourceEntryA->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde cataloguswaarde van postzegel A.',
        'needs_review' => true,
    ]);

    // Maak twee cataloguswaarden voor postzegel B aan.
    $sourceValueB1 = $sourceEntryB->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Nulwaarde van postzegel B.',
        'needs_review' => false,
    ]);

    $sourceValueB2 = $sourceEntryB->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '15.25',
        'currency' => 'USD',
        'notes' => 'Gestempelde cataloguswaarde van postzegel B.',
        'needs_review' => false,
    ]);

    // Maak één cataloguswaarde voor postzegel C aan.
    $sourceValueC1 = $sourceEntryC->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '95.50',
        'currency' => 'CHF',
        'notes' => null,
        'needs_review' => true,
    ]);

    // Postzegel D krijgt bewust geen cataloguswaarden.

    // Verzamel alle oorspronkelijke catalogusvermeldingen.
    $sourceEntries = [
        $sourceEntryA,
        $sourceEntryB,
        $sourceEntryC,
        $sourceEntryD,
    ];

    // Verzamel alle oorspronkelijke cataloguswaarden.
    $sourceValues = [
        $sourceValueA1,
        $sourceValueA2,
        $sourceValueB1,
        $sourceValueB2,
        $sourceValueC1,
    ];

    // Bewaar volledige snapshots van alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampA,
        $stampB,
        $stampC,
        $stampD,
        ...$sourceEntries,
        ...$sourceValues,
    ];

    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(4);
    expect(StampCatalogValue::count())->toBe(5);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Controleer het aantal waarden per oorspronkelijke vermelding.
    expect($sourceEntryA->values()->count())->toBe(2);
    expect($sourceEntryB->values()->count())->toBe(2);
    expect($sourceEntryC->values()->count())->toBe(1);
    expect($sourceEntryD->values()->count())->toBe(0);

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Initialiseer het duplicatieformulier.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-07-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie met meerdere wijzigingslogs.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat vóór bevestiging niets is opgeslagen.
        expect(session()->has($sessionKey))->toBeTrue();
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(4);
        expect(StampCatalogValue::count())->toBe(5);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Bevestig de duplicatie.
        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('backend.catalogs.editions.index', $this->catalog)
            );

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();

        // Controleer de nieuwe databasesituatie.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(8);
        expect(StampCatalogValue::count())->toBe(10);

        // Alle wijzigingen moeten in precies één batch staan.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(9);

        // Haal de nieuw aangemaakte doeleditie op.
        $targetEdition = $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->sole();

        // Controleer het aantal vermeldingen en waarden per editie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(4);
        expect($this->sourceEdition->stampValues()->count())->toBe(5);

        expect($targetEdition->stampEntries()->count())->toBe(4);
        expect($targetEdition->stampValues()->count())->toBe(5);

        // Haal de enige geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de gegevens van de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($targetEdition->id);
        expect($batch->user_id)->toBe($this->admin->id);
        expect($batch->user_name)->toBe($this->admin->name);
        expect($batch->operation)->toBe('duplicate');
        expect($batch->catalog_name)->toBe($this->catalog->name);
        expect($batch->catalog_edition_name)->toBe('Editie 2027');

        expect($batch->description)
            ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026');

        // Controleer het totale aantal wijzigingslogs binnen de batch.
        expect($batch->logs()->count())->toBe(9);

        // Controleer het aantal logs per recordtype.
        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(4);

        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(5);

        // Controleer dat alle logs uitsluitend aanmaakacties bevatten.
        expect(
            $batch->logs()
                ->where('action', 'created')
                ->count()
        )->toBe(9);

        expect(
            $batch->logs()
                ->where('action', 'updated')
                ->count()
        )->toBe(0);

        expect(
            $batch->logs()
                ->where('action', 'deleted')
                ->count()
        )->toBe(0);

        // Controleer iedere gekopieerde catalogusvermelding
        // en alle bijbehorende cataloguswaarden.
        foreach ($sourceEntries as $sourceEntry) {
            // Haal de overeenkomstige nieuwe catalogusvermelding op.
            $targetEntry = $targetEdition->stampEntries()
                ->where('stamp_id', $sourceEntry->stamp_id)
                ->sole();

            // Controleer de gekopieerde catalogusvermelding.
            expect($targetEntry->id)->not->toBe($sourceEntry->id);

            expect($targetEntry->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($targetEntry->stamp_id)
                ->toBe($sourceEntry->stamp_id);

            expect($targetEntry->catalog_number)
                ->toBe($sourceEntry->catalog_number);

            expect($targetEntry->notes)
                ->toBe($sourceEntry->notes);

            // Controleer dat er precies één wijzigingslog
            // voor deze catalogusvermelding bestaat.
            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $targetEntry->id)
                ->sole();

            // Controleer de volledige geschiedenisregistratie.
            expect($entryLog->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($entryLog->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($entryLog->stamp_id)
                ->toBe($sourceEntry->stamp_id);

            expect($entryLog->condition_id)->toBeNull();
            expect($entryLog->action)->toBe('created');
            expect($entryLog->old_values)->toBeNull();

            expect($entryLog->new_values)
                ->toEqual($targetEntry->fresh()->getAttributes());

            // Controleer het aantal gekopieerde cataloguswaarden.
            expect($targetEntry->values()->count())
                ->toBe($sourceEntry->values()->count());

            // Controleer iedere bijbehorende cataloguswaarde.
            foreach ($sourceEntry->values()->get() as $sourceValue) {
                // Haal de overeenkomstige nieuwe cataloguswaarde op.
                $targetValue = $targetEntry->values()
                    ->where('condition_id', $sourceValue->condition_id)
                    ->sole();

                // Controleer de gekopieerde cataloguswaarde.
                expect($targetValue->id)->not->toBe($sourceValue->id);

                expect($targetValue->stamp_catalog_entry_id)
                    ->toBe($targetEntry->id);

                expect($targetValue->condition_id)
                    ->toBe($sourceValue->condition_id);

                expect($targetValue->catalog_value)
                    ->toBe($sourceValue->catalog_value);

                expect($targetValue->currency)
                    ->toBe($sourceValue->currency);

                expect($targetValue->notes)
                    ->toBe($sourceValue->notes);

                expect($targetValue->needs_review)->toBeTrue();

                // Controleer dat er precies één wijzigingslog
                // voor deze cataloguswaarde bestaat.
                $valueLog = $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $targetValue->id)
                    ->sole();

                // Controleer de volledige geschiedenisregistratie.
                expect($valueLog->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($valueLog->catalog_edition_id)
                    ->toBe($targetEdition->id);

                expect($valueLog->stamp_id)
                    ->toBe($sourceEntry->stamp_id);

                expect($valueLog->condition_id)
                    ->toBe($sourceValue->condition_id);

                expect($valueLog->action)->toBe('created');
                expect($valueLog->old_values)->toBeNull();

                expect($valueLog->new_values)
                    ->toEqual($targetValue->fresh()->getAttributes());
            }
        }

        // Controleer het aantal wijzigingslogs per postzegel.
        $expectedLogCounts = [
            $stampA->id => 3,
            $stampB->id => 3,
            $stampC->id => 2,
            $stampD->id => 1,
        ];

        foreach ($expectedLogCounts as $stampId => $expectedCount) {
            expect(
                $batch->logs()
                    ->where('stamp_id', $stampId)
                    ->count()
            )->toBe($expectedCount);
        }

        // Controleer het behoud van de nulwaarde.
        $targetEntryB = $targetEdition->stampEntries()
            ->where('stamp_id', $stampB->id)
            ->sole();

        $targetZeroValue = $targetEntryB->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($targetZeroValue->catalog_value)->toBe('0.00');
        expect($targetZeroValue->needs_review)->toBeTrue();

        // Controleer dat de catalogusvermelding zonder waarden
        // eveneens succesvol is gekopieerd.
        $targetEntryD = $targetEdition->stampEntries()
            ->where('stamp_id', $stampD->id)
            ->sole();

        expect($targetEntryD->catalog_number)->toBe('LW-018-D');
        expect($targetEntryD->notes)->toBeNull();
        expect($targetEntryD->values()->count())->toBe(0);

        // Controleer dat hiervoor geen waarderingslog bestaat.
        expect(
            $batch->logs()
                ->where('stamp_id', $stampD->id)
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(0);

        // Controleer dat alle wijzigingslogs
        // aan dezelfde geschiedenisbatch zijn gekoppeld.
        expect(
            CatalogChangeLog::query()
                ->where('catalog_change_batch_id', $batch->id)
                ->count()
        )->toBe(9);

        // Controleer dat alle wijzigingslogs
        // aan dezelfde doeleditie zijn gekoppeld.
        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $targetEdition->id)
                ->count()
        )->toBe(9);

        // Controleer dat er geen geschiedenis
        // voor de oorspronkelijke broneditie bestaat.
        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        // Controleer dat alle oorspronkelijke modellen
        // volledig ongewijzigd zijn gebleven.
        foreach ($originalModels as $index => $model) {
            expect($model->fresh()->getAttributes())
                ->toEqual($originalSnapshots[$index]);
        }

        // Controleer nogmaals het totale aantal batches en logs.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(9);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een niet-ingelogde bezoeker wordt bij toegang tot de aanmaakpagina van catalogusedities doorgestuurd naar de inlogpagina', function () {
    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-019',
        'notes' => 'Catalogusvermelding voor authenticatiecontrole.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '195.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor authenticatiecontrole.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide gebruikers.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();

    // Controleer dat er geen gebruiker is ingelogd.
    $this->assertGuest();

    expect(auth()->check())->toBeFalse();
    expect(auth()->id())->toBeNull();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bepaal de URL van de beveiligde aanmaakpagina.
    $createUrl = route(
        'backend.catalogs.editions.create',
        $this->catalog
    );

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Probeer als niet-ingelogde bezoeker
        // de beveiligde aanmaakpagina te openen.
        $response = $this->get($createUrl);

        // Controleer dat toegang tot de pagina wordt geweigerd
        // en de bezoeker wordt doorgestuurd naar de inlogpagina.
        $response
            ->assertStatus(302)
            ->assertRedirect(route('login'));

        // Controleer dat Laravel de oorspronkelijk
        // aangevraagde URL in de sessie heeft opgeslagen.
        $response->assertSessionHas('url.intended', $createUrl);

        // Controleer dat de bezoeker nog steeds niet is ingelogd.
        $this->assertGuest();

        expect(auth()->check())->toBeFalse();
        expect(auth()->id())->toBeNull();

        // Controleer dat er geen nieuwe catalogus of editie bestaat.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(1);

        // Controleer dat er geen catalogusgegevens zijn aangemaakt.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de oorspronkelijke cataloguswaarde
        // volledig ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat beide gebruikers volledig ongewijzigd zijn.
        expect($this->admin->fresh()->getAttributes())
            ->toEqual($originalAdmin);

        expect($this->user->fresh()->getAttributes())
            ->toEqual($originalUser);

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een ingelogde gewone gebruiker heeft toegang tot de aanmaakpagina van catalogusedities zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->user);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-020',
        'notes' => 'Catalogusvermelding voor toegang door een gewone gebruiker.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '205.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor toegang door een gewone gebruiker.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide gebruikers.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();

    // Controleer dat de gewone gebruiker is ingelogd.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de administrator niet is ingelogd.
    expect(auth()->id())->not->toBe($this->admin->id);
    expect($this->admin->is_admin)->toBeTrue();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bepaal de URL van de beveiligde aanmaakpagina.
    $createUrl = route(
        'backend.catalogs.editions.create',
        $this->catalog
    );

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Open de aanmaakpagina als gewone gebruiker.
        $response = $this->get($createUrl);

        // Controleer dat de pagina succesvol wordt weergegeven.
        $response->assertOk();

        // Controleer dat de juiste Livewire-component aanwezig is.
        $response->assertSeeLivewire($this->componentName);

        // Controleer dat de naam van de juiste catalogus zichtbaar is.
        $response->assertSee($this->catalog->name);

        // Controleer dat de gewone gebruiker ingelogd blijft.
        $this->assertAuthenticatedAs($this->user);

        expect(auth()->check())->toBeTrue();
        expect(auth()->id())->toBe($this->user->id);
        expect(auth()->user()->is_admin)->toBeFalse();

        // Controleer dat er geen doorverwijzing
        // naar de inlogpagina is voorbereid.
        $response->assertSessionMissing('url.intended');

        // Controleer dat er geen nieuwe catalogus of editie bestaat.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(1);

        // Controleer dat er geen catalogusgegevens zijn aangemaakt.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat de broneditie haar eigen gegevens behoudt.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1);
        expect($this->sourceEdition->stampValues()->count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de oorspronkelijke cataloguswaarde
        // volledig ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat beide gebruikers volledig ongewijzigd zijn.
        expect($this->admin->fresh()->getAttributes())
            ->toEqual($originalAdmin);

        expect($this->user->fresh()->getAttributes())
            ->toEqual($originalUser);

        // Controleer dat de gewone gebruiker
        // geen administratorrechten heeft gekregen.
        expect($this->user->fresh()->is_admin)->toBeFalse();
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een ingelogde administrator heeft toegang tot de aanmaakpagina van catalogusedities zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-021',
        'notes' => 'Catalogusvermelding voor toegang door een administrator.',
    ]);

    // Maak een cataloguswaarde in de broneditie aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '215.75',
        'currency' => 'EUR',
        'notes' => 'Cataloguswaarde voor toegang door een administrator.',
        'needs_review' => false,
    ]);

    // Bewaar volledige snapshots van de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar volledige snapshots van beide gebruikers.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();

    // Controleer dat de administrator is ingelogd.
    $this->assertAuthenticatedAs($this->admin);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->admin->id);
    expect(auth()->user()->is_admin)->toBeTrue();

    // Controleer dat de gewone gebruiker niet is ingelogd.
    expect(auth()->id())->not->toBe($this->user->id);
    expect($this->user->is_admin)->toBeFalse();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(1);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bepaal de URL van de beveiligde aanmaakpagina.
    $createUrl = route(
        'backend.catalogs.editions.create',
        $this->catalog
    );

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Open de aanmaakpagina als administrator.
        $response = $this->get($createUrl);

        // Controleer dat de pagina succesvol wordt weergegeven.
        $response->assertOk();

        // Controleer dat de juiste Livewire-component aanwezig is.
        $response->assertSeeLivewire($this->componentName);

        // Controleer dat de naam van de juiste catalogus zichtbaar is.
        $response->assertSee($this->catalog->name);

        // Controleer dat de administrator ingelogd blijft.
        $this->assertAuthenticatedAs($this->admin);

        expect(auth()->check())->toBeTrue();
        expect(auth()->id())->toBe($this->admin->id);
        expect(auth()->user()->is_admin)->toBeTrue();

        // Controleer dat er geen doorverwijzing
        // naar de inlogpagina is voorbereid.
        $response->assertSessionMissing('url.intended');

        // Controleer dat er geen nieuwe catalogus of editie bestaat.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(1);

        // Controleer dat er geen catalogusgegevens zijn aangemaakt.
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(1);

        // Controleer dat de broneditie haar eigen gegevens behoudt.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1);
        expect($this->sourceEdition->stampValues()->count())->toBe(1);

        // Controleer dat de oorspronkelijke catalogus ongewijzigd is.
        expect($this->catalog->fresh()->getAttributes())
            ->toEqual($originalCatalog);

        // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toEqual($originalSourceEdition);

        // Controleer dat de oorspronkelijke postzegel ongewijzigd is.
        expect($stamp->fresh()->getAttributes())
            ->toEqual($originalStamp);

        // Controleer dat de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toEqual($originalSourceEntry);

        // Controleer dat de oorspronkelijke cataloguswaarde
        // volledig ongewijzigd is.
        expect($sourceValue->fresh()->getAttributes())
            ->toEqual($originalSourceValue);

        // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
        expect($sourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat beide gebruikers volledig ongewijzigd zijn.
        expect($this->admin->fresh()->getAttributes())
            ->toEqual($originalAdmin);

        expect($this->user->fresh()->getAttributes())
            ->toEqual($originalUser);

        // Controleer dat de bestaande gebruikersrechten behouden blijven.
        expect($this->admin->fresh()->is_admin)->toBeTrue();
        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Controleer dat er geen geschiedenis is geregistreerd.
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een gewone gebruiker mag zonder administratorrechten een cataloguseditie dupliceren inclusief geschiedenisregistratie', function () {
    $this->actingAs($this->user);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-022',
        'notes' => 'Catalogusvermelding voor duplicatie door een gewone gebruiker.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourceValuePostfris = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '225.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $sourceValueGestempeld = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '35.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stamp,
        $sourceEntry,
        $sourceValuePostfris,
        $sourceValueGestempeld,
        $this->admin,
        $this->user,
    ];

    // Bewaar volledige snapshots van de oorspronkelijke modellen.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer dat de gewone gebruiker is ingelogd.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de administrator niet is ingelogd.
    expect(auth()->id())->not->toBe($this->admin->id);
    expect($this->admin->is_admin)->toBeTrue();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Open de aanmaakpagina als gewone gebruiker.
        $response = $this->get(
            route('backend.catalogs.editions.create', $this->catalog)
        );

        // Controleer de toegang tot de aanmaakpagina.
        $response->assertOk();
        $response->assertSeeLivewire($this->componentName);
        $response->assertSee($this->catalog->name);

        // Controleer dat de gewone gebruiker ingelogd blijft.
        $this->assertAuthenticatedAs($this->user);

        expect(auth()->user()->is_admin)->toBeFalse();

        // Controleer dat het openen van de pagina
        // geen gegevenswijzigingen veroorzaakt.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Initialiseer het Livewire-duplicatieformulier.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-08-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie uitgevoerd door een gewone gebruiker.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat de bevestigingshash aanwezig is.
        expect(session()->has($sessionKey))->toBeTrue();

        // Controleer dat vóór bevestiging niets is opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Bevestig de duplicatie als gewone gebruiker.
        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('backend.catalogs.editions.index', $this->catalog)
            );

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();

        // Controleer dat dezelfde gewone gebruiker ingelogd blijft.
        $this->assertAuthenticatedAs($this->user);

        expect(auth()->check())->toBeTrue();
        expect(auth()->id())->toBe($this->user->id);
        expect(auth()->user()->is_admin)->toBeFalse();

        // Controleer de nieuwe databasesituatie.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(4);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(3);

        // Haal de nieuw aangemaakte doeleditie op.
        $targetEdition = $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->sole();

        // Controleer de gegevens van de doeleditie.
        expect($targetEdition->id)
            ->not->toBe($this->sourceEdition->id);

        expect($targetEdition->catalog_id)
            ->toBe($this->catalog->id);

        expect($targetEdition->edition_name)->toBe('Editie 2027');
        expect($targetEdition->edition_year)->toBe(2027);
        expect($targetEdition->currency)->toBe('EUR');

        expect($targetEdition->notes)
            ->toBe('Duplicatie uitgevoerd door een gewone gebruiker.');

        expect(
            Carbon::parse(
                $targetEdition->getRawOriginal('publication_date')
            )->toDateString()
        )->toBe('2027-08-15');

        // Controleer het aantal vermeldingen en waarden.
        expect($targetEdition->stampEntries()->count())->toBe(1);
        expect($targetEdition->stampValues()->count())->toBe(2);

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer de gekopieerde catalogusvermelding.
        expect($targetEntry->id)->not->toBe($sourceEntry->id);

        expect($targetEntry->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($targetEntry->stamp_id)->toBe($stamp->id);
        expect($targetEntry->catalog_number)->toBe('LW-022');
        expect($targetEntry->notes)->toBe($sourceEntry->notes);

        // Haal de geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de gegevens van de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($batch->user_id)->toBe($this->user->id);
        expect($batch->user_id)->not->toBe($this->admin->id);
        expect($batch->user_name)->toBe($this->user->name);
        expect($batch->operation)->toBe('duplicate');
        expect($batch->catalog_name)->toBe($this->catalog->name);
        expect($batch->catalog_edition_name)->toBe('Editie 2027');

        expect($batch->description)
            ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026');

        // Controleer de gebruikersrelatie van de geschiedenisbatch.
        expect($batch->user->id)->toBe($this->user->id);
        expect($batch->user->is_admin)->toBeFalse();

        // Controleer het aantal wijzigingslogs.
        expect($batch->logs()->count())->toBe(3);

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

        expect(
            $batch->logs()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        // Haal het wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $targetEntry->id)
            ->sole();

        // Controleer de geschiedenisregistratie van de vermelding.
        expect($entryLog->catalog_change_batch_id)->toBe($batch->id);
        expect($entryLog->catalog_edition_id)->toBe($targetEdition->id);
        expect($entryLog->stamp_id)->toBe($stamp->id);
        expect($entryLog->condition_id)->toBeNull();
        expect($entryLog->action)->toBe('created');
        expect($entryLog->old_values)->toBeNull();

        expect($entryLog->new_values)
            ->toEqual($targetEntry->fresh()->getAttributes());

        // Controleer beide gekopieerde cataloguswaarden.
        $sourceValues = [
            $sourceValuePostfris,
            $sourceValueGestempeld,
        ];

        foreach ($sourceValues as $sourceValue) {
            // Haal de overeenkomstige gekopieerde waarde op.
            $targetValue = $targetEntry->values()
                ->where('condition_id', $sourceValue->condition_id)
                ->sole();

            // Controleer de gekopieerde cataloguswaarde.
            expect($targetValue->id)->not->toBe($sourceValue->id);

            expect($targetValue->stamp_catalog_entry_id)
                ->toBe($targetEntry->id);

            expect($targetValue->condition_id)
                ->toBe($sourceValue->condition_id);

            expect($targetValue->catalog_value)
                ->toBe($sourceValue->catalog_value);

            expect($targetValue->currency)
                ->toBe($sourceValue->currency);

            expect($targetValue->notes)
                ->toBe($sourceValue->notes);

            expect($targetValue->needs_review)->toBeTrue();

            // Haal het bijbehorende wijzigingslog op.
            $valueLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('record_id', $targetValue->id)
                ->sole();

            // Controleer de geschiedenisregistratie.
            expect($valueLog->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($valueLog->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($valueLog->stamp_id)->toBe($stamp->id);

            expect($valueLog->condition_id)
                ->toBe($sourceValue->condition_id);

            expect($valueLog->action)->toBe('created');
            expect($valueLog->old_values)->toBeNull();

            expect($valueLog->new_values)
                ->toEqual($targetValue->fresh()->getAttributes());
        }

        // Controleer dat de administrator geen geschiedenisbatch heeft.
        expect(
            CatalogChangeBatch::query()
                ->where('user_id', $this->admin->id)
                ->count()
        )->toBe(0);

        // Controleer dat er geen anonieme geschiedenisbatch bestaat.
        expect(
            CatalogChangeBatch::query()
                ->whereNull('user_id')
                ->count()
        )->toBe(0);

        // Controleer dat er geen geschiedenis voor de broneditie bestaat.
        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        // Controleer dat alle oorspronkelijke modellen
        // volledig ongewijzigd zijn gebleven.
        foreach ($originalModels as $index => $model) {
            expect($model->fresh()->getAttributes())
                ->toEqual($originalSnapshots[$index]);
        }

        // Controleer dat de oorspronkelijke controlemarkeringen behouden zijn.
        expect($sourceValuePostfris->fresh()->needs_review)->toBeFalse();
        expect($sourceValueGestempeld->fresh()->needs_review)->toBeTrue();

        // Controleer dat de gebruikersrechten ongewijzigd zijn.
        expect($this->user->fresh()->is_admin)->toBeFalse();
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        // Controleer nogmaals het totale aantal batches en logs.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(3);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een administrator mag een cataloguseditie dupliceren inclusief geschiedenisregistratie met behoud van de bestaande gebruikersrechten', function () {
    $this->actingAs($this->admin);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-023',
        'notes' => 'Catalogusvermelding voor duplicatie door een administrator.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourceValuePostfris = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '235.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $sourceValueGestempeld = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '45.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stamp,
        $sourceEntry,
        $sourceValuePostfris,
        $sourceValueGestempeld,
        $this->admin,
        $this->user,
    ];

    // Bewaar volledige snapshots van de oorspronkelijke modellen.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer dat de administrator is ingelogd.
    $this->assertAuthenticatedAs($this->admin);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->admin->id);
    expect(auth()->user()->is_admin)->toBeTrue();

    // Controleer dat de gewone gebruiker niet is ingelogd.
    expect(auth()->id())->not->toBe($this->user->id);
    expect($this->user->is_admin)->toBeFalse();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd zodat ongewenste wijzigingen
    // van oorspronkelijke tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Open de aanmaakpagina als administrator.
        $response = $this->get(
            route('backend.catalogs.editions.create', $this->catalog)
        );

        // Controleer de toegang tot de aanmaakpagina.
        $response->assertOk();
        $response->assertSeeLivewire($this->componentName);
        $response->assertSee($this->catalog->name);

        // Controleer dat de administrator ingelogd blijft.
        $this->assertAuthenticatedAs($this->admin);

        expect(auth()->user()->is_admin)->toBeTrue();

        // Controleer dat het openen van de pagina
        // geen gegevenswijzigingen veroorzaakt.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Initialiseer het Livewire-duplicatieformulier.
        $component = Livewire::test($this->componentName, [
            'catalog' => $this->catalog,
        ])
            ->set('edition_name', 'Editie 2027')
            ->set('edition_year', 2027)
            ->set('publication_date', '2027-09-15')
            ->set('currency', 'EUR')
            ->set('notes', 'Duplicatie uitgevoerd door een administrator.')
            ->set('source_edition_id', $this->sourceEdition->id)
            ->set('copy_catalog_values', true);

        // Bepaal de sessiesleutel van deze component.
        $sessionKey = 'catalog_edition_duplication_pending.'
            .$component->instance()->getId();

        // Bereid de duplicatie voor.
        $component
            ->call('save')
            ->assertHasNoErrors();

        // Controleer dat de bevestigingshash aanwezig is.
        expect(session()->has($sessionKey))->toBeTrue();

        // Controleer dat vóór bevestiging niets is opgeslagen.
        expect(CatalogEdition::count())->toBe(1);
        expect(StampCatalogEntry::count())->toBe(1);
        expect(StampCatalogValue::count())->toBe(2);
        expect(CatalogChangeBatch::count())->toBe(0);
        expect(CatalogChangeLog::count())->toBe(0);

        // Bevestig de duplicatie als administrator.
        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route('backend.catalogs.editions.index', $this->catalog)
            );

        // Controleer dat de bevestigingshash is verwijderd.
        expect(session()->has($sessionKey))->toBeFalse();

        // Controleer dat dezelfde administrator ingelogd blijft.
        $this->assertAuthenticatedAs($this->admin);

        expect(auth()->check())->toBeTrue();
        expect(auth()->id())->toBe($this->admin->id);
        expect(auth()->user()->is_admin)->toBeTrue();

        // Controleer de nieuwe databasesituatie.
        expect(Catalog::count())->toBe(1);
        expect(CatalogEdition::count())->toBe(2);
        expect(StampCatalogEntry::count())->toBe(2);
        expect(StampCatalogValue::count())->toBe(4);
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(3);

        // Haal de nieuw aangemaakte doeleditie op.
        $targetEdition = $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->sole();

        // Controleer de gegevens van de doeleditie.
        expect($targetEdition->id)
            ->not->toBe($this->sourceEdition->id);

        expect($targetEdition->catalog_id)
            ->toBe($this->catalog->id);

        expect($targetEdition->edition_name)->toBe('Editie 2027');
        expect($targetEdition->edition_year)->toBe(2027);
        expect($targetEdition->currency)->toBe('EUR');

        expect($targetEdition->notes)
            ->toBe('Duplicatie uitgevoerd door een administrator.');

        expect(
            Carbon::parse(
                $targetEdition->getRawOriginal('publication_date')
            )->toDateString()
        )->toBe('2027-09-15');

        // Controleer het aantal vermeldingen en waarden.
        expect($targetEdition->stampEntries()->count())->toBe(1);
        expect($targetEdition->stampValues()->count())->toBe(2);

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer de gekopieerde catalogusvermelding.
        expect($targetEntry->id)->not->toBe($sourceEntry->id);

        expect($targetEntry->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($targetEntry->stamp_id)->toBe($stamp->id);
        expect($targetEntry->catalog_number)->toBe('LW-023');
        expect($targetEntry->notes)->toBe($sourceEntry->notes);

        // Haal de geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de gegevens van de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($batch->user_id)->toBe($this->admin->id);
        expect($batch->user_id)->not->toBe($this->user->id);
        expect($batch->user_name)->toBe($this->admin->name);
        expect($batch->operation)->toBe('duplicate');
        expect($batch->catalog_name)->toBe($this->catalog->name);
        expect($batch->catalog_edition_name)->toBe('Editie 2027');

        expect($batch->description)
            ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026');

        // Controleer de gebruikersrelatie van de geschiedenisbatch.
        expect($batch->user->id)->toBe($this->admin->id);
        expect($batch->user->is_admin)->toBeTrue();

        // Controleer het aantal wijzigingslogs.
        expect($batch->logs()->count())->toBe(3);

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

        expect(
            $batch->logs()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        // Haal het wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $targetEntry->id)
            ->sole();

        // Controleer de geschiedenisregistratie van de vermelding.
        expect($entryLog->catalog_change_batch_id)->toBe($batch->id);
        expect($entryLog->catalog_edition_id)->toBe($targetEdition->id);
        expect($entryLog->stamp_id)->toBe($stamp->id);
        expect($entryLog->condition_id)->toBeNull();
        expect($entryLog->action)->toBe('created');
        expect($entryLog->old_values)->toBeNull();

        expect($entryLog->new_values)
            ->toEqual($targetEntry->fresh()->getAttributes());

        // Controleer beide gekopieerde cataloguswaarden.
        $sourceValues = [
            $sourceValuePostfris,
            $sourceValueGestempeld,
        ];

        foreach ($sourceValues as $sourceValue) {
            // Haal de overeenkomstige gekopieerde waarde op.
            $targetValue = $targetEntry->values()
                ->where('condition_id', $sourceValue->condition_id)
                ->sole();

            // Controleer de gekopieerde cataloguswaarde.
            expect($targetValue->id)->not->toBe($sourceValue->id);

            expect($targetValue->stamp_catalog_entry_id)
                ->toBe($targetEntry->id);

            expect($targetValue->condition_id)
                ->toBe($sourceValue->condition_id);

            expect($targetValue->catalog_value)
                ->toBe($sourceValue->catalog_value);

            expect($targetValue->currency)
                ->toBe($sourceValue->currency);

            expect($targetValue->notes)
                ->toBe($sourceValue->notes);

            expect($targetValue->needs_review)->toBeTrue();

            // Haal het bijbehorende wijzigingslog op.
            $valueLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('record_id', $targetValue->id)
                ->sole();

            // Controleer de geschiedenisregistratie.
            expect($valueLog->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($valueLog->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($valueLog->stamp_id)->toBe($stamp->id);

            expect($valueLog->condition_id)
                ->toBe($sourceValue->condition_id);

            expect($valueLog->action)->toBe('created');
            expect($valueLog->old_values)->toBeNull();

            expect($valueLog->new_values)
                ->toEqual($targetValue->fresh()->getAttributes());
        }

        // Controleer dat de gewone gebruiker geen geschiedenisbatch heeft.
        expect(
            CatalogChangeBatch::query()
                ->where('user_id', $this->user->id)
                ->count()
        )->toBe(0);

        // Controleer dat er geen anonieme geschiedenisbatch bestaat.
        expect(
            CatalogChangeBatch::query()
                ->whereNull('user_id')
                ->count()
        )->toBe(0);

        // Controleer dat er geen geschiedenis voor de broneditie bestaat.
        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->sourceEdition->id)
                ->count()
        )->toBe(0);

        // Controleer dat alle oorspronkelijke modellen
        // volledig ongewijzigd zijn gebleven.
        foreach ($originalModels as $index => $model) {
            expect($model->fresh()->getAttributes())
                ->toEqual($originalSnapshots[$index]);
        }

        // Controleer dat de oorspronkelijke controlemarkeringen behouden zijn.
        expect($sourceValuePostfris->fresh()->needs_review)->toBeFalse();
        expect($sourceValueGestempeld->fresh()->needs_review)->toBeTrue();

        // Controleer dat de gebruikersrechten ongewijzigd zijn.
        expect($this->admin->fresh()->is_admin)->toBeTrue();
        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Controleer nogmaals het totale aantal batches en logs.
        expect(CatalogChangeBatch::count())->toBe(1);
        expect(CatalogChangeLog::count())->toBe(3);

        // Controleer dat er geen actieve geschiedenisbatch achterblijft.
        expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
            ->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een niet-geauthenticeerd HTTP-updateverzoek naar de Livewire-duplicatiecomponent wordt geweigerd zonder gegevenswijzigingen en geschiedenisregistratie', function () {
    $this->actingAs($this->user);

    // Maak een postzegel voor de bestaande broneditie aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding in de broneditie aan.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'LW-024',
        'notes' => 'Catalogusvermelding voor de HTTP-authenticatietest.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourceValuePostfris = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '245.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $sourceValueGestempeld = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '55.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stamp,
        $sourceEntry,
        $sourceValuePostfris,
        $sourceValueGestempeld,
        $this->admin,
        $this->user,
    ];

    // Bewaar volledige snapshots van de oorspronkelijke modellen.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de oorspronkelijke authenticatiesituatie.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer de oorspronkelijke databasesituatie.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bepaal de URL van de oorspronkelijke aanmaakpagina.
    $createUrl = route(
        'backend.catalogs.editions.create',
        $this->catalog
    );

    // Bepaal de URL van het geregistreerde Livewire-update-endpoint.
    $updateUrl = route('default-livewire.update');

    // Open de oorspronkelijke aanmaakpagina als ingelogde gebruiker.
    $response = $this->get($createUrl);

    $response->assertOk();
    $response->assertSeeLivewire($this->componentName);
    $response->assertSee($this->catalog->name);

    // Lees de HTML-inhoud van het HTTP-antwoord uit.
    $html = $response->getContent();

    expect($html)->toBeString();
    expect($html)->not->toBeEmpty();

    // Initialiseer de HTML-parser.
    $document = new DOMDocument;

    // Bewaar de oorspronkelijke libxml-foutafhandeling.
    $previousLibxmlSetting = libxml_use_internal_errors(true);

    try {
        $loaded = $document->loadHTML(
            $html,
            LIBXML_NONET
        );

        expect($loaded)->toBeTrue();
    } finally {
        libxml_clear_errors();

        libxml_use_internal_errors($previousLibxmlSetting);
    }

    // Initialiseer XPath voor het zoeken naar Livewire-componenten.
    $xpath = new DOMXPath($document);

    // Zoek alle HTML-elementen met een wire:snapshot-attribuut.
    $snapshotNodes = $xpath->query(
        '//*[@*[name()="wire:snapshot"]]'
    );

    expect($snapshotNodes)->not->toBeFalse();
    expect($snapshotNodes->length)->toBeGreaterThan(0);

    // Verzamel snapshots die bij onze duplicatiecomponent horen.
    $matchingSnapshots = [];

    foreach ($snapshotNodes as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }

        $snapshotJson = $node->getAttribute('wire:snapshot');

        $decodedSnapshot = json_decode(
            $snapshotJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (
            ($decodedSnapshot['memo']['name'] ?? null)
            !== $this->componentName
        ) {
            continue;
        }

        $matchingSnapshots[] = [
            'json' => $snapshotJson,
            'decoded' => $decodedSnapshot,
        ];
    }

    // Controleer dat precies één passende component is gevonden.
    expect($matchingSnapshots)->toHaveCount(1);

    // Bewaar de oorspronkelijke JSON-snapshot.
    $snapshot = $matchingSnapshots[0]['json'];

    // Bewaar de gedecodeerde snapshot voor aanvullende controles.
    $decodedSnapshot = $matchingSnapshots[0]['decoded'];

    // Controleer de oorspronkelijke snapshotstructuur.
    expect($snapshot)->toBeString();
    expect($snapshot)->not->toBeEmpty();

    expect($decodedSnapshot)->toHaveKeys([
        'data',
        'memo',
        'checksum',
    ]);

    expect($decodedSnapshot['data'])->toBeArray();
    expect($decodedSnapshot['memo'])->toBeArray();
    expect($decodedSnapshot['checksum'])->toBeString();
    expect($decodedSnapshot['checksum'])->not->toBeEmpty();

    // Controleer de identiteit van de Livewire-component.
    expect($decodedSnapshot['memo']['name'])
        ->toBe($this->componentName);

    expect($decodedSnapshot['memo']['id'])->toBeString();
    expect($decodedSnapshot['memo']['id'])->not->toBeEmpty();

    // Controleer de oorspronkelijke paginaroute.
    expect($decodedSnapshot['memo']['path'])
        ->toBe(ltrim(
            parse_url($createUrl, PHP_URL_PATH),
            '/'
        ));

    expect($decodedSnapshot['memo']['method'])->toBe('GET');

    // Controleer dat het ophalen van de snapshot
    // geen gegevenswijzigingen heeft veroorzaakt.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    foreach ($originalModels as $index => $model) {
        expect($model->fresh()->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    expect($this->history->getActiveBatch())->toBeNull();

    // Stel geldige formuliergegevens samen voor de duplicatie.
    $updates = [
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'publication_date' => '2027-10-15',
        'currency' => 'EUR',
        'notes' => 'Duplicatie voorbereid via een rechtstreeks HTTP-updateverzoek.',
        'source_edition_id' => $this->sourceEdition->id,
        'copy_catalog_values' => true,
    ];

    // Stel een geldig Livewire-updateverzoek samen.
    $authenticatedPayload = [
        'components' => [
            [
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => [
                    [
                        'method' => 'save',
                        'params' => [],
                    ],
                ],
            ],
        ],
    ];

    // Bepaal de sessiesleutel voor de duplicatiebevestiging.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$decodedSnapshot['memo']['id'];

    // Er mag nog geen voorbereide duplicatie aanwezig zijn.
    expect(session()->has($sessionKey))->toBeFalse();

    // Voer het geauthenticeerde HTTP-updateverzoek uit.
    $authenticatedResponse = $this->postJson(
        $updateUrl,
        $authenticatedPayload,
        [
            'X-Livewire' => 'true',
        ]
    );

    // Controleer dat het geldige verzoek wordt geaccepteerd.
    $authenticatedResponse->assertOk();

    $authenticatedResponse->assertJsonStructure([
        'components' => [
            [
                'snapshot',
                'effects',
            ],
        ],
    ]);

    // Controleer dat de gebruiker nog steeds ingelogd is.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de duplicatie daadwerkelijk is voorbereid.
    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Haal de bijgewerkte componentsnapshot uit het HTTP-antwoord.
    $preparedSnapshot = $authenticatedResponse->json(
        'components.0.snapshot'
    );

    expect($preparedSnapshot)->toBeString();
    expect($preparedSnapshot)->not->toBeEmpty();

    $decodedPreparedSnapshot = json_decode(
        $preparedSnapshot,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Controleer de structuur van de bijgewerkte snapshot.
    expect($decodedPreparedSnapshot)->toHaveKeys([
        'data',
        'memo',
        'checksum',
    ]);

    expect($decodedPreparedSnapshot['checksum'])->toBeString();
    expect($decodedPreparedSnapshot['checksum'])->not->toBeEmpty();

    // Controleer dat het dezelfde Livewire-component betreft.
    expect($decodedPreparedSnapshot['memo']['id'])
        ->toBe($decodedSnapshot['memo']['id']);

    expect($decodedPreparedSnapshot['memo']['name'])
        ->toBe($this->componentName);

    expect($decodedPreparedSnapshot['memo']['path'])
        ->toBe($decodedSnapshot['memo']['path']);

    expect($decodedPreparedSnapshot['memo']['method'])
        ->toBe('GET');

    // Controleer dat alle formuliergegevens correct zijn verwerkt.
    foreach ($updates as $property => $value) {
        expect($decodedPreparedSnapshot['data'][$property])
            ->toBe($value);
    }

    // Controleer dat de duplicatie nog niet is uitgevoerd.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat de toekomstige doeleditie nog niet bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        expect($model->fresh()->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Er mag nog geen actieve geschiedenisbatch aanwezig zijn.
    expect($this->history->getActiveBatch())->toBeNull();

    // Stel een geldig HTTP-verzoek voor de duplicatiebevestiging samen.
    $unauthenticatedPayload = [
        'components' => [
            [
                'snapshot' => $preparedSnapshot,
                'updates' => [],
                'calls' => [
                    [
                        'method' => 'confirmSave',
                        'params' => [],
                    ],
                ],
            ],
        ],
    ];

    // Controleer dat de voorbereide duplicatie nog aanwezig is.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(session()->get($sessionKey))->toBe($confirmationHash);

    // Reset de interne Livewire-status tussen afzonderlijke HTTP-verzoeken.
    // Volg hiermee het resetmechanisme van Livewire's eigen testimplementatie.
    Livewire::flushState();

    // Leg de oorspronkelijke gebruikersgegevens vast voordat we uitloggen.
    $userBeforeLogout = $this->user->fresh()->getAttributes();

    // Verwijder uitsluitend de authenticatie van de gebruiker.
    // Behoud de sessie en de voorbereide duplicatiebevestiging.
    auth()->guard('web')->logout();

    // Haal de gebruikersgegevens na het uitloggen opnieuw op.
    $userAfterLogout = $this->user->fresh()->getAttributes();

    // Laravel vernieuwt bij het uitloggen de remember-token.
    expect($userAfterLogout['remember_token'])->toBeString();
    expect($userAfterLogout['remember_token'])->toHaveLength(60);
    expect($userAfterLogout['remember_token'])
        ->not->toBe($userBeforeLogout['remember_token']);

    // Controleer dat alle overige gebruikersgegevens ongewijzigd zijn,
    // met uitzondering van het eventueel bijgewerkte updated_at-veld.
    expect(
        Arr::except(
            $userAfterLogout,
            ['remember_token', 'updated_at']
        )
    )->toEqual(
        Arr::except(
            $userBeforeLogout,
            ['remember_token', 'updated_at']
        )
    );

    // Actualiseer uitsluitend de verwachte snapshot van de uitgelogde gebruiker.
    // Latere wijzigingen door het geweigerde HTTP-verzoek blijven detecteerbaar.
    foreach ($originalModels as $index => $model) {
        if ($model instanceof User && $model->is($this->user)) {
            $originalSnapshots[$index] = $userAfterLogout;
        }
    }

    // Verwijder eventueel eerder geïnitialiseerde authenticatieguards.
    app('auth')->forgetGuards();

    // Controleer dat de gebruiker daadwerkelijk niet meer ingelogd is.
    $this->assertGuest('web');

    expect(auth()->check())->toBeFalse();
    expect(auth()->id())->toBeNull();
    expect(auth()->user())->toBeNull();

    // Controleer dat de bevestigingshash behouden is gebleven.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(session()->get($sessionKey))->toBe($confirmationHash);

    // Voer het niet-geauthenticeerde HTTP-updateverzoek uit.
    $unauthenticatedResponse = $this->postJson(
        $updateUrl,
        $unauthenticatedPayload,
        [
            'X-Livewire' => 'true',
        ]
    );

    // Het verzoek moet vanwege ontbrekende authenticatie worden geweigerd.
    $unauthenticatedResponse->assertUnauthorized();

    // Controleer dat de gebruiker niet opnieuw is geauthenticeerd.
    $this->assertGuest('web');

    expect(auth()->check())->toBeFalse();
    expect(auth()->id())->toBeNull();
    expect(auth()->user())->toBeNull();

    // De bevestigingshash moet nog aanwezig zijn.
    // Hiermee controleren we dat confirmSave niet is uitgevoerd.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(session()->get($sessionKey))->toBe($confirmationHash);

    // Controleer dat er geen nieuwe catalogus of cataloguseditie is aangemaakt.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);

    // Controleer dat er geen catalogusvermeldingen of waarden zijn gedupliceerd.
    expect(StampCatalogEntry::count())->toBe(1);
    expect(StampCatalogValue::count())->toBe(2);

    // Controleer dat de beoogde doeleditie niet bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        expect($model->fresh()->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat er geen actieve geschiedenisbatch is achtergebleven.
    expect($this->history->getActiveBatch())->toBeNull();
});

test('een fout tijdens het dupliceren van catalogusvermeldingen vanuit Livewire draait de volledige cataloguseditie en geschiedenisregistratie terug', function () {
    // Authenticeer een gewone gebruiker.
    $this->actingAs($this->user);

    // Maak drie oorspronkelijke postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampThree = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak de oorspronkelijke catalogusvermeldingen aan.
    $sourceEntryOne = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'LW-025',
        'notes' => 'Eerste catalogusvermelding voor de rollbacktest.',
    ]);

    $sourceEntryTwo = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'LW-026',
        'notes' => 'Tweede catalogusvermelding voor de rollbacktest.',
    ]);

    $sourceEntryThree = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampThree->id,
        'catalog_number' => 'LW-027',
        'notes' => 'Derde catalogusvermelding voor de rollbacktest.',
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampOne,
        $stampTwo,
        $stampThree,
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
        $this->admin,
        $this->user,
    ];

    // Leg de volledige oorspronkelijke databasegegevens vast.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de oorspronkelijke authenticatie.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer de oorspronkelijke databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(0);

    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(0);

    // Controleer dat de beoogde doeleditie nog niet bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer de oorspronkelijke geschiedenisstatus.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Initialiseer de Livewire-component en vul het duplicatieformulier in.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Rollbacktest tijdens het dupliceren van catalogusvermeldingen.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', false);

    // Bepaal de sessiesleutel voor de duplicatiebevestiging.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat er nog geen duplicatiebevestiging aanwezig is.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor zonder deze definitief te bevestigen.
    $component->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatiebevestiging correct is voorbereid.
    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat de gebruiker nog steeds geauthenticeerd is.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de voorbereiding geen databasewijzigingen veroorzaakt.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(0);

    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(0);

    // Controleer dat de beoogde doeleditie nog niet bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        expect($model->fresh()->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat er nog geen geschiedenisregistratie heeft plaatsgevonden.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)->not->toBeNull();

    // Maak een tijdelijke kopie met behoud van bestaande listeners.
    $temporaryDispatcher = clone $originalDispatcher;

    // Controleer dat de tijdelijke dispatcher een afzonderlijk object is.
    expect($temporaryDispatcher)->not->toBe($originalDispatcher);

    // Houd het aantal aanmaakpogingen van doelvermeldingen bij.
    $targetEntryCreationAttempts = 0;

    // Leg straks de tijdelijke databasestatus vóór de geforceerde fout vast.
    $stateBeforeFailure = [];

    // Controleer de oorspronkelijke uitgangswaarden.
    expect($targetEntryCreationAttempts)->toBe(0);
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke dispatcher nog actief is.
    expect(StampCatalogEntry::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Registreer een tijdelijke listener voor nieuwe catalogusvermeldingen.
    $temporaryDispatcher->listen(
        'eloquent.creating: '.StampCatalogEntry::class,
        function (StampCatalogEntry $entry) use (
            &$targetEntryCreationAttempts,
            &$stateBeforeFailure
        ) {
            // Negeer eventuele aanmaakpogingen binnen de broneditie.
            if ((int) $entry->catalog_edition_id === (int) $this->sourceEdition->id) {
                return;
            }

            // Verhoog het aantal aanmaakpogingen binnen de doeleditie.
            $targetEntryCreationAttempts++;

            // Laat de eerste catalogusvermelding normaal aanmaken.
            if ($targetEntryCreationAttempts !== 2) {
                return;
            }

            // Haal de tijdelijk aangemaakte doeleditie op.
            $targetEdition = CatalogEdition::query()
                ->findOrFail($entry->catalog_edition_id);

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = app(CatalogChangeHistoryService::class)
                ->getActiveBatch();

            // Leg de tijdelijke databasestatus vast voordat de fout optreedt.
            $stateBeforeFailure = [
                'catalog_count' => Catalog::count(),
                'edition_count' => CatalogEdition::count(),
                'stamp_count' => Stamp::count(),
                'entry_count' => StampCatalogEntry::count(),
                'value_count' => StampCatalogValue::count(),

                'target_edition_id' => $targetEdition->id,
                'target_edition_attributes' => $targetEdition->getAttributes(),

                'target_entry_count' => $targetEdition->stampEntries()->count(),
                'target_entries' => $targetEdition->stampEntries()
                    ->get()
                    ->map(fn (StampCatalogEntry $targetEntry) => $targetEntry->getAttributes())
                    ->all(),

                'source_entry_count' => $this->sourceEdition->stampEntries()->count(),

                'batch_count' => CatalogChangeBatch::count(),
                'log_count' => CatalogChangeLog::count(),

                'active_batch_id' => $activeBatch?->id,
                'active_batch_attributes' => $activeBatch?->getAttributes(),

                'active_batch_logs' => $activeBatch?->logs()
                    ->get()
                    ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
                    ->all(),

                'failing_entry_attributes' => $entry->getAttributes(),
            ];

            // Veroorzaak bewust een fout tijdens de tweede aanmaakpoging.
            throw new RuntimeException(
                'Geforceerde fout tijdens het dupliceren van de tweede catalogusvermelding vanuit Livewire.'
            );
        }
    );

    // Controleer dat de tijdelijke listener nog niet is uitgevoerd.
    expect($targetEntryCreationAttempts)->toBe(0);
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke dispatcher nog steeds actief is.
    expect(StampCatalogEntry::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat de voorbereide duplicatiebevestiging nog aanwezig is.
    expect(session()->has($sessionKey))->toBeTrue();
    expect(session()->get($sessionKey))->toBe($confirmationHash);

    // Verplaats de testtijd om eventuele wijzigingen aan timestamps
    // van oorspronkelijke modellen later te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Activeer de tijdelijke Eloquent-eventdispatcher.
        StampCatalogEntry::setEventDispatcher($temporaryDispatcher);

        // Controleer dat de tijdelijke dispatcher daadwerkelijk actief is.
        expect(StampCatalogEntry::getEventDispatcher())
            ->toBe($temporaryDispatcher);

        // Zorg dat de geforceerde uitzondering rechtstreeks beschikbaar is.
        $this->withoutExceptionHandling();

        // Bevestig de duplicatie en verwacht de bewust veroorzaakte fout.
        expect(
            fn () => $component->call('confirmSave')
        )->toThrow(
            RuntimeException::class,
            'Geforceerde fout tijdens het dupliceren van de tweede catalogusvermelding vanuit Livewire.'
        );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogEntry::setEventDispatcher($originalDispatcher);

        // Herstel de normale exceptionafhandeling.
        $this->withExceptionHandling();

        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer dat de oorspronkelijke dispatcher opnieuw actief is.
    expect(StampCatalogEntry::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat precies twee aanmaakpogingen hebben plaatsgevonden.
    expect($targetEntryCreationAttempts)->toBe(2);

    // Controleer dat de tijdelijke databasestatus is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer de tijdelijke databaseaantallen vóór de geforceerde fout.
    expect($stateBeforeFailure['catalog_count'])->toBe(1);
    expect($stateBeforeFailure['edition_count'])->toBe(2);
    expect($stateBeforeFailure['stamp_count'])->toBe(3);
    expect($stateBeforeFailure['entry_count'])->toBe(4);
    expect($stateBeforeFailure['value_count'])->toBe(0);

    // Controleer de tijdelijke catalogusvermeldingen.
    expect($stateBeforeFailure['source_entry_count'])->toBe(3);
    expect($stateBeforeFailure['target_entry_count'])->toBe(1);
    expect($stateBeforeFailure['target_entries'])->toHaveCount(1);

    // Controleer dat de tijdelijk aangemaakte doeleditie een eigen ID heeft.
    $temporaryTargetEditionId = $stateBeforeFailure['target_edition_id'];

    expect($temporaryTargetEditionId)->toBeInt();
    expect($temporaryTargetEditionId)->not->toBe($this->sourceEdition->id);

    // Controleer de gegevens van de tijdelijk aangemaakte doeleditie.
    $temporaryTargetEdition = $stateBeforeFailure['target_edition_attributes'];

    expect($temporaryTargetEdition['id'])->toBe($temporaryTargetEditionId);
    expect((int) $temporaryTargetEdition['catalog_id'])->toBe($this->catalog->id);
    expect($temporaryTargetEdition['edition_name'])->toBe('Editie 2027');
    expect((int) $temporaryTargetEdition['edition_year'])->toBe(2027);
    expect($temporaryTargetEdition['currency'])->toBe('EUR');
    expect($temporaryTargetEdition['notes'])
        ->toBe('Rollbacktest tijdens het dupliceren van catalogusvermeldingen.');

    expect(
        Carbon::parse(
            $temporaryTargetEdition['publication_date']
        )->toDateString()
    )->toBe('2027-01-15');

    // Controleer de eerste daadwerkelijk aangemaakte doelvermelding.
    $temporaryTargetEntry = $stateBeforeFailure['target_entries'][0];

    expect($temporaryTargetEntry['id'])->toBeInt();
    expect((int) $temporaryTargetEntry['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    // Zoek de bijbehorende oorspronkelijke bronvermelding.
    $matchingSourceEntry = collect([
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
    ])->first(
        fn (StampCatalogEntry $sourceEntry) => (int) $sourceEntry->stamp_id === (int) $temporaryTargetEntry['stamp_id']
    );

    expect($matchingSourceEntry)->toBeInstanceOf(StampCatalogEntry::class);

    // Controleer dat de eerste doelvermelding correct is gekopieerd.
    expect($temporaryTargetEntry['id'])->not->toBe($matchingSourceEntry->id);
    expect((int) $temporaryTargetEntry['stamp_id'])
        ->toBe((int) $matchingSourceEntry->stamp_id);
    expect($temporaryTargetEntry['catalog_number'])
        ->toBe($matchingSourceEntry->catalog_number);
    expect($temporaryTargetEntry['notes'])
        ->toBe($matchingSourceEntry->notes);

    // Controleer de tweede catalogusvermelding waarbij de fout optrad.
    $failingEntry = $stateBeforeFailure['failing_entry_attributes'];

    expect((int) $failingEntry['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect((int) $failingEntry['stamp_id'])
        ->not->toBe((int) $temporaryTargetEntry['stamp_id']);

    // Zoek de oorspronkelijke bronvermelding van de mislukte aanmaakpoging.
    $matchingFailingSourceEntry = collect([
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
    ])->first(
        fn (StampCatalogEntry $sourceEntry) => (int) $sourceEntry->stamp_id === (int) $failingEntry['stamp_id']
    );

    expect($matchingFailingSourceEntry)
        ->toBeInstanceOf(StampCatalogEntry::class);

    expect($failingEntry['catalog_number'])
        ->toBe($matchingFailingSourceEntry->catalog_number);
    expect($failingEntry['notes'])
        ->toBe($matchingFailingSourceEntry->notes);

    // Controleer dat de tweede doelvermelding nog geen database-ID had.
    expect($failingEntry['id'] ?? null)->toBeNull();

    // Controleer de tijdelijke geschiedenisregistratie.
    expect($stateBeforeFailure['batch_count'])->toBe(1);
    expect($stateBeforeFailure['log_count'])->toBe(1);
    expect($stateBeforeFailure['active_batch_id'])->toBeInt();
    expect($stateBeforeFailure['active_batch_attributes'])->toBeArray();
    expect($stateBeforeFailure['active_batch_logs'])->toHaveCount(1);

    // Haal de vastgelegde tijdelijke geschiedenisbatch op.
    $temporaryBatch = $stateBeforeFailure['active_batch_attributes'];

    // Controleer de identificatie van de geschiedenisbatch.
    expect($temporaryBatch['id'])->toBeInt();
    expect($temporaryBatch['id'])
        ->toBe($stateBeforeFailure['active_batch_id']);

    // Controleer dat de geschiedenisbatch aan de doeleditie is gekoppeld.
    expect((int) $temporaryBatch['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect((int) $temporaryBatch['catalog_edition_id'])
        ->not->toBe($this->sourceEdition->id);

    // Controleer de geregistreerde gebruiker.
    expect((int) $temporaryBatch['user_id'])
        ->toBe($this->user->id);

    expect($temporaryBatch['user_name'])
        ->toBe($this->user->name);

    // Controleer het type bewerking en de omschrijving.
    expect($temporaryBatch['operation'])->toBe('duplicate');

    expect($temporaryBatch['description'])
        ->toBe(
            'Duplicatie van catalogusvermeldingen vanuit '
            .$this->sourceEdition->edition_name
        );

    // Controleer de historische catalogus- en editienamen.
    expect($temporaryBatch['catalog_name'])
        ->toBe($this->catalog->name);

    expect($temporaryBatch['catalog_edition_name'])
        ->toBe('Editie 2027');

    // Controleer de timestamps van de tijdelijke geschiedenisbatch.
    expect($temporaryBatch['created_at'])->not->toBeNull();
    expect($temporaryBatch['updated_at'])->not->toBeNull();

    // Haal de vastgelegde tijdelijke wijzigingslog op.
    $temporaryLog = $stateBeforeFailure['active_batch_logs'][0];

    // Controleer de identificatie van de wijzigingslog.
    expect($temporaryLog['id'])->toBeInt();

    expect((int) $temporaryLog['catalog_change_batch_id'])
        ->toBe($temporaryBatch['id']);

    // Controleer de koppeling met de doeleditie.
    expect((int) $temporaryLog['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect((int) $temporaryLog['catalog_edition_id'])
        ->not->toBe($this->sourceEdition->id);

    // Controleer de koppeling met de oorspronkelijke postzegel.
    expect((int) $temporaryLog['stamp_id'])
        ->toBe((int) $temporaryTargetEntry['stamp_id']);

    // Controleer dat niet-relevante koppelingen leeg zijn.
    expect($temporaryLog['stamp_series_id'])->toBeNull();
    expect($temporaryLog['condition_id'])->toBeNull();

    // Controleer het geregistreerde recordtype.
    expect($temporaryLog['record_type'])
        ->toBe('stamp_catalog_entry');

    // Controleer dat de wijzigingslog naar de doelvermelding verwijst.
    expect((int) $temporaryLog['record_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect((int) $temporaryLog['record_id'])
        ->not->toBe($matchingSourceEntry->id);

    // Controleer het geregistreerde wijzigingstype.
    expect($temporaryLog['action'])->toBe('created');

    // Bij een nieuw aangemaakt record bestaan geen oude waarden.
    expect($temporaryLog['old_values'])->toBeNull();

    // Decodeer de vastgelegde nieuwe waarden vanuit het JSON-veld.
    $temporaryNewValues = json_decode(
        $temporaryLog['new_values'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Controleer de volledige inhoud van de geregistreerde nieuwe waarden.
    expect($temporaryNewValues)->toBeArray();

    expect($temporaryNewValues)
        ->toEqual($temporaryTargetEntry);

    // Controleer de belangrijkste afzonderlijke geregistreerde waarden.
    expect((int) $temporaryNewValues['id'])
        ->toBe($temporaryTargetEntry['id']);

    expect((int) $temporaryNewValues['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect((int) $temporaryNewValues['stamp_id'])
        ->toBe((int) $matchingSourceEntry->stamp_id);

    expect($temporaryNewValues['catalog_number'])
        ->toBe($matchingSourceEntry->catalog_number);

    expect($temporaryNewValues['notes'])
        ->toBe($matchingSourceEntry->notes);

    // Controleer de timestamps van de tijdelijke wijzigingslog.
    expect($temporaryLog['created_at'])->not->toBeNull();
    expect($temporaryLog['updated_at'])->not->toBeNull();

    // Controleer dat de oorspronkelijke gebruiker geauthenticeerd is gebleven.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat alle oorspronkelijke databaseaantallen zijn hersteld.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(0);

    // Controleer dat de oorspronkelijke broneditie nog bestaat.
    expect(
        CatalogEdition::query()
            ->whereKey($this->sourceEdition->id)
            ->exists()
    )->toBeTrue();

    // Controleer dat alle oorspronkelijke bronvermeldingen behouden zijn.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(0);

    // Controleer dat de tijdelijk aangemaakte doeleditie is teruggedraaid.
    expect(
        CatalogEdition::query()
            ->whereKey($temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de beoogde doeleditie ook niet onder een ander ID bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijk aangemaakte doelvermelding is teruggedraaid.
    expect(
        StampCatalogEntry::query()
            ->whereKey($temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat er geen catalogusvermeldingen voor de doeleditie bestaan.
    expect(
        StampCatalogEntry::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat de tijdelijke geschiedenisbatch is teruggedraaid.
    expect(
        CatalogChangeBatch::query()
            ->whereKey($temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijke wijzigingslog is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($temporaryLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat er geen geschiedenisregistratie is achtergebleven.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat er geen geschiedenis voor de doeleditie bestaat.
    expect(
        CatalogChangeBatch::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    expect(
        CatalogChangeLog::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de actieve geschiedenisbatch is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer dat de gebruikte duplicatiebevestiging is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat er geen succesmelding is achtergebleven.
    expect(session()->has('success'))->toBeFalse();

    // Controleer nogmaals dat de oorspronkelijke dispatcher actief is.
    expect(StampCatalogEntry::getEventDispatcher())
        ->toBe($originalDispatcher);
});

test('een fout tijdens het dupliceren van cataloguswaarden vanuit Livewire draait de volledige cataloguseditie en geschiedenisregistratie terug', function () {
    // Authenticeer een gewone gebruiker.
    $this->actingAs($this->user);

    // Maak drie oorspronkelijke postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampThree = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak drie oorspronkelijke catalogusvermeldingen aan.
    $sourceEntryOne = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'LW-028',
        'notes' => 'Eerste catalogusvermelding voor de waarderollbacktest.',
    ]);

    $sourceEntryTwo = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'LW-029',
        'notes' => 'Tweede catalogusvermelding voor de waarderollbacktest.',
    ]);

    $sourceEntryThree = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampThree->id,
        'catalog_number' => 'LW-030',
        'notes' => 'Derde catalogusvermelding voor de waarderollbacktest.',
    ]);

    // Maak de oorspronkelijke cataloguswaarden van de eerste postzegel aan.
    $sourceValueOne = $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '12.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    $sourceValueTwo = $sourceEntryOne->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '4.25',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke cataloguswaarden van de tweede postzegel aan.
    $sourceValueThree = $sourceEntryTwo->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '18.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de tweede postzegel.',
        'needs_review' => false,
    ]);

    $sourceValueFour = $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke cataloguswaarden van de derde postzegel aan.
    $sourceValueFive = $sourceEntryThree->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de derde postzegel.',
        'needs_review' => true,
    ]);

    $sourceValueSix = $sourceEntryThree->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '7.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de derde postzegel.',
        'needs_review' => false,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampOne,
        $stampTwo,
        $stampThree,
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $sourceValueFive,
        $sourceValueSix,
        $this->admin,
        $this->user,
    ];

    // Leg de volledige oorspronkelijke databaseattributen vast.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de oorspronkelijke authenticatie.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer het aantal oorspronkelijke modelsnapshots.
    expect($originalModels)->toHaveCount(19);
    expect($originalSnapshots)->toHaveCount(19);

    // Controleer de oorspronkelijke databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer de oorspronkelijke gegevens van de broneditie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer het aantal waarden per oorspronkelijke catalogusvermelding.
    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);
    expect($sourceEntryThree->values()->count())->toBe(2);

    // Controleer dat de beoogde doeleditie nog niet bestaat.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat er nog geen geschiedenisregistratie bestaat.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Bereid het Livewire-duplicatieformulier inclusief cataloguswaarden voor.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Rollbacktest tijdens het dupliceren van cataloguswaarden.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Controleer de ingestelde duplicatieopties.
    $component
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    // Bepaal de sessiesleutel voor de duplicatiebevestiging.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat er nog geen duplicatiebevestiging bestaat.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor zonder deze definitief te bevestigen.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatiebevestiging is opgeslagen.
    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat de oorspronkelijke gebruiker geauthenticeerd blijft.
    $this->assertAuthenticatedAs($this->user);

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de voorbereiding geen nieuwe records heeft aangemaakt.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer dat alle oorspronkelijke brongegevens behouden zijn.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer dat de doeleditie nog niet is aangemaakt.
    expect(
        $this->catalog->editions()
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modelattributen ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        expect($model->fresh()->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat er nog geen geschiedenisregistratie is aangemaakt.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);
    expect($this->history->getActiveBatch())->toBeNull();

    // Haal de oorspronkelijke Eloquent-eventdispatcher op.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Controleer dat de oorspronkelijke dispatcher beschikbaar is.
    expect($originalDispatcher)->not->toBeNull();

    // Maak een tijdelijke kopie met behoud van bestaande eventlisteners.
    $temporaryDispatcher = clone $originalDispatcher;

    // Controleer dat de tijdelijke dispatcher een afzonderlijk object is.
    expect($temporaryDispatcher)->not->toBe($originalDispatcher);

    // Initialiseer de teller voor aanmaakpogingen van doelwaarden.
    $targetValueCreationAttempts = 0;

    // Initialiseer de opslag voor de tijdelijke databasestatus.
    $stateBeforeFailure = [];

    // Controleer de oorspronkelijke toestand van de teller.
    expect($targetValueCreationAttempts)->toBe(0);

    // Controleer dat er nog geen tijdelijke databasestatus is vastgelegd.
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke dispatcher nog steeds actief is.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Voeg een tijdelijke eventlistener toe voor nieuwe cataloguswaarden.
    $temporaryDispatcher->listen(
        'eloquent.creating: '.StampCatalogValue::class,
        function (StampCatalogValue $value) use (
            &$targetValueCreationAttempts,
            &$stateBeforeFailure
        ) {
            // Haal de bijbehorende catalogusvermelding op.
            $targetEntry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            // Negeer eventuele aanmaakpogingen binnen de oorspronkelijke broneditie.
            if (
                (int) $targetEntry->catalog_edition_id
                === (int) $this->sourceEdition->id
            ) {
                return;
            }

            // Registreer de aanmaakpoging van een doelwaarde.
            $targetValueCreationAttempts++;

            // Laat de eerste cataloguswaarde normaal opslaan.
            if ($targetValueCreationAttempts !== 2) {
                return;
            }

            // Haal de tijdelijk aangemaakte doeleditie op.
            $targetEdition = CatalogEdition::query()
                ->findOrFail($targetEntry->catalog_edition_id);

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = app(CatalogChangeHistoryService::class)
                ->getActiveBatch();

            // Leg de volledige tijdelijke databasestatus vast.
            $stateBeforeFailure = [
                'catalog_count' => Catalog::count(),
                'edition_count' => CatalogEdition::count(),
                'stamp_count' => Stamp::count(),
                'entry_count' => StampCatalogEntry::count(),
                'value_count' => StampCatalogValue::count(),

                'target_edition_id' => $targetEdition->id,
                'target_edition_attributes' => $targetEdition->getAttributes(),

                'target_entry_count' => $targetEdition->stampEntries()->count(),

                'target_entries' => $targetEdition->stampEntries()
                    ->get()
                    ->map(
                        fn (StampCatalogEntry $entry) => $entry->getAttributes()
                    )
                    ->all(),

                'target_value_count' => $targetEdition->stampValues()->count(),

                'target_values' => $targetEdition->stampValues()
                    ->get()
                    ->map(
                        fn (StampCatalogValue $targetValue) => $targetValue->getAttributes()
                    )
                    ->all(),

                'source_entry_count' => $this->sourceEdition
                    ->stampEntries()
                    ->count(),

                'source_value_count' => $this->sourceEdition
                    ->stampValues()
                    ->count(),

                'batch_count' => CatalogChangeBatch::count(),
                'log_count' => CatalogChangeLog::count(),

                'active_batch_id' => $activeBatch?->id,
                'active_batch_attributes' => $activeBatch?->getAttributes(),

                'active_batch_logs' => $activeBatch?->logs()
                    ->get()
                    ->map(
                        fn (CatalogChangeLog $log) => $log->getAttributes()
                    )
                    ->all(),

                'failing_value_attributes' => $value->getAttributes(),
            ];

            // Veroorzaak bewust een fout voordat de tweede doelwaarde wordt opgeslagen.
            throw new RuntimeException(
                'Geforceerde fout tijdens het dupliceren van de tweede cataloguswaarde vanuit Livewire.'
            );
        }
    );

    // Controleer dat de tijdelijke eventlistener nog niet is uitgevoerd.
    expect($targetValueCreationAttempts)->toBe(0);
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke eventdispatcher nog actief is.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat de oorspronkelijke duplicatiebevestiging nog beschikbaar is.
    expect(session()->has($sessionKey))->toBeTrue();

    expect(session()->get($sessionKey))
        ->toBe($confirmationHash);

    // Verplaats de testtijd om eventuele timestampwijzigingen te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Activeer de tijdelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($temporaryDispatcher);

        // Controleer dat de tijdelijke dispatcher daadwerkelijk actief is.
        expect(StampCatalogValue::getEventDispatcher())
            ->toBe($temporaryDispatcher);

        // Schakel de standaard exception handling tijdelijk uit.
        $this->withoutExceptionHandling();

        // Bevestig de duplicatie en verwacht de geforceerde uitzondering.
        expect(fn () => $component->call('confirmSave'))
            ->toThrow(
                RuntimeException::class,
                'Geforceerde fout tijdens het dupliceren van de tweede cataloguswaarde vanuit Livewire.'
            );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de standaard exception handling.
        $this->withExceptionHandling();

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer dat de oorspronkelijke eventdispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat precies twee aanmaakpogingen hebben plaatsgevonden.
    expect($targetValueCreationAttempts)->toBe(2);

    // Controleer dat de tijdelijke databasestatus is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer de tijdelijke databaseaantallen vóór de geforceerde fout.
    expect($stateBeforeFailure['catalog_count'])->toBe(1);
    expect($stateBeforeFailure['edition_count'])->toBe(2);
    expect($stateBeforeFailure['stamp_count'])->toBe(3);
    expect($stateBeforeFailure['entry_count'])->toBe(4);
    expect($stateBeforeFailure['value_count'])->toBe(7);

    // Controleer de tijdelijke aantallen van de broneditie.
    expect($stateBeforeFailure['source_entry_count'])->toBe(3);
    expect($stateBeforeFailure['source_value_count'])->toBe(6);

    // Controleer de tijdelijke aantallen van de doeleditie.
    expect($stateBeforeFailure['target_entry_count'])->toBe(1);
    expect($stateBeforeFailure['target_entries'])->toHaveCount(1);
    expect($stateBeforeFailure['target_value_count'])->toBe(1);
    expect($stateBeforeFailure['target_values'])->toHaveCount(1);

    // Controleer dat de tijdelijk aangemaakte doeleditie een eigen ID heeft.
    $temporaryTargetEditionId = $stateBeforeFailure['target_edition_id'];

    expect($temporaryTargetEditionId)->toBeInt();
    expect($temporaryTargetEditionId)->not->toBe($this->sourceEdition->id);

    // Controleer de gegevens van de tijdelijk aangemaakte doeleditie.
    $temporaryTargetEdition = $stateBeforeFailure['target_edition_attributes'];

    expect($temporaryTargetEdition['id'])->toBe($temporaryTargetEditionId);

    expect((int) $temporaryTargetEdition['catalog_id'])
        ->toBe($this->catalog->id);

    expect($temporaryTargetEdition['edition_name'])->toBe('Editie 2027');
    expect((int) $temporaryTargetEdition['edition_year'])->toBe(2027);
    expect($temporaryTargetEdition['currency'])->toBe('EUR');

    expect($temporaryTargetEdition['notes'])
        ->toBe('Rollbacktest tijdens het dupliceren van cataloguswaarden.');

    expect(
        Carbon::parse(
            $temporaryTargetEdition['publication_date']
        )->toDateString()
    )->toBe('2027-01-15');

    // Controleer de daadwerkelijk aangemaakte doelvermelding.
    $temporaryTargetEntry = $stateBeforeFailure['target_entries'][0];

    expect($temporaryTargetEntry['id'])->toBeInt();

    expect((int) $temporaryTargetEntry['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    // Zoek de bijbehorende oorspronkelijke bronvermelding.
    $matchingSourceEntry = collect([
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
    ])->first(
        fn (StampCatalogEntry $sourceEntry) => (int) $sourceEntry->stamp_id === (int) $temporaryTargetEntry['stamp_id']
    );

    expect($matchingSourceEntry)->toBeInstanceOf(StampCatalogEntry::class);

    // Controleer dat de doelvermelding correct is gekopieerd.
    expect($temporaryTargetEntry['id'])
        ->not->toBe($matchingSourceEntry->id);

    expect((int) $temporaryTargetEntry['stamp_id'])
        ->toBe((int) $matchingSourceEntry->stamp_id);

    expect($temporaryTargetEntry['catalog_number'])
        ->toBe($matchingSourceEntry->catalog_number);

    expect($temporaryTargetEntry['notes'])
        ->toBe($matchingSourceEntry->notes);

    // Controleer de eerste daadwerkelijk opgeslagen doelwaarde.
    $temporaryTargetValue = $stateBeforeFailure['target_values'][0];

    expect($temporaryTargetValue['id'])->toBeInt();

    expect((int) $temporaryTargetValue['stamp_catalog_entry_id'])
        ->toBe($temporaryTargetEntry['id']);

    // Zoek de oorspronkelijke bronwaarde op basis van vermelding en conditie.
    $matchingSourceValue = collect([
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $sourceValueFive,
        $sourceValueSix,
    ])->first(
        fn (StampCatalogValue $sourceValue) => (int) $sourceValue->stamp_catalog_entry_id === $matchingSourceEntry->id
            && (int) $sourceValue->condition_id === (int) $temporaryTargetValue['condition_id']
    );

    expect($matchingSourceValue)->toBeInstanceOf(StampCatalogValue::class);

    // Controleer dat de doelwaarde een afzonderlijk record is.
    expect($temporaryTargetValue['id'])
        ->not->toBe($matchingSourceValue->id);

    // Controleer dat de eerste doelwaarde correct is gekopieerd.
    expect((int) $temporaryTargetValue['condition_id'])
        ->toBe((int) $matchingSourceValue->condition_id);

    expect((float) $temporaryTargetValue['catalog_value'])
        ->toBe((float) $matchingSourceValue->catalog_value);

    expect($temporaryTargetValue['currency'])
        ->toBe($matchingSourceValue->currency);

    expect($temporaryTargetValue['notes'])
        ->toBe($matchingSourceValue->notes);

    // Controleer dat de gedupliceerde waarde een controlemarkering heeft.
    expect(in_array(
        $temporaryTargetValue['needs_review'],
        [true, 1],
        true
    ))->toBeTrue();

    // Controleer de tweede cataloguswaarde waarbij de fout optrad.
    $failingValue = $stateBeforeFailure['failing_value_attributes'];

    expect((int) $failingValue['stamp_catalog_entry_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect((int) $failingValue['condition_id'])
        ->not->toBe((int) $temporaryTargetValue['condition_id']);

    // Zoek de oorspronkelijke bronwaarde van de mislukte aanmaakpoging.
    $matchingFailingSourceValue = collect([
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $sourceValueFive,
        $sourceValueSix,
    ])->first(
        fn (StampCatalogValue $sourceValue) => (int) $sourceValue->stamp_catalog_entry_id === $matchingSourceEntry->id
            && (int) $sourceValue->condition_id === (int) $failingValue['condition_id']
    );

    expect($matchingFailingSourceValue)
        ->toBeInstanceOf(StampCatalogValue::class);

    // Controleer de gegevens van de mislukte aanmaakpoging.
    expect((float) $failingValue['catalog_value'])
        ->toBe((float) $matchingFailingSourceValue->catalog_value);

    expect($failingValue['currency'])
        ->toBe($matchingFailingSourceValue->currency);

    expect($failingValue['notes'])
        ->toBe($matchingFailingSourceValue->notes);

    expect(in_array(
        $failingValue['needs_review'],
        [true, 1],
        true
    ))->toBeTrue();

    // Controleer dat de tweede doelwaarde nog geen database-ID had.
    expect($failingValue['id'] ?? null)->toBeNull();

    // Controleer de tijdelijke geschiedenisregistratie.
    expect($stateBeforeFailure['batch_count'])->toBe(1);
    expect($stateBeforeFailure['log_count'])->toBe(2);
    expect($stateBeforeFailure['active_batch_id'])->toBeInt();
    expect($stateBeforeFailure['active_batch_attributes'])->toBeArray();
    expect($stateBeforeFailure['active_batch_logs'])->toHaveCount(2);

    // Haal de vastgelegde tijdelijke geschiedenisbatch op.
    $temporaryBatch = $stateBeforeFailure['active_batch_attributes'];

    // Controleer de identificatie van de geschiedenisbatch.
    expect($temporaryBatch['id'])->toBeInt();

    expect($temporaryBatch['id'])
        ->toBe($stateBeforeFailure['active_batch_id']);

    // Controleer de koppeling met de doeleditie.
    expect((int) $temporaryBatch['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect((int) $temporaryBatch['catalog_edition_id'])
        ->not->toBe($this->sourceEdition->id);

    // Controleer de geregistreerde gebruiker.
    expect((int) $temporaryBatch['user_id'])
        ->toBe($this->user->id);

    expect($temporaryBatch['user_name'])
        ->toBe($this->user->name);

    // Controleer het type bewerking en de omschrijving.
    expect($temporaryBatch['operation'])->toBe('duplicate');

    expect($temporaryBatch['description'])
        ->toBe(
            'Duplicatie van catalogusvermeldingen vanuit '
            .$this->sourceEdition->edition_name
        );

    // Controleer de historische catalogus- en editienamen.
    expect($temporaryBatch['catalog_name'])
        ->toBe($this->catalog->name);

    expect($temporaryBatch['catalog_edition_name'])
        ->toBe('Editie 2027');

    // Controleer de timestamps van de geschiedenisbatch.
    expect($temporaryBatch['created_at'])->not->toBeNull();
    expect($temporaryBatch['updated_at'])->not->toBeNull();

    // Verzamel beide tijdelijke wijzigingslogs.
    $temporaryLogs = collect($stateBeforeFailure['active_batch_logs']);

    expect($temporaryLogs)->toHaveCount(2);

    // Controleer dat precies één log per recordtype bestaat.
    expect(
        $temporaryLogs->where('record_type', 'stamp_catalog_entry')
    )->toHaveCount(1);

    expect(
        $temporaryLogs->where('record_type', 'stamp_catalog_value')
    )->toHaveCount(1);

    // Haal beide wijzigingslogs afzonderlijk op.
    $temporaryEntryLog = $temporaryLogs
        ->firstWhere('record_type', 'stamp_catalog_entry');

    $temporaryValueLog = $temporaryLogs
        ->firstWhere('record_type', 'stamp_catalog_value');

    expect($temporaryEntryLog)->toBeArray();
    expect($temporaryValueLog)->toBeArray();

    // Controleer dat beide wijzigingslogs een eigen ID hebben.
    expect($temporaryEntryLog['id'])->toBeInt();
    expect($temporaryValueLog['id'])->toBeInt();

    expect($temporaryEntryLog['id'])
        ->not->toBe($temporaryValueLog['id']);

    // Controleer de gemeenschappelijke eigenschappen van beide wijzigingslogs.
    foreach ($temporaryLogs as $temporaryLog) {
        expect((int) $temporaryLog['catalog_change_batch_id'])
            ->toBe($temporaryBatch['id']);

        expect((int) $temporaryLog['catalog_edition_id'])
            ->toBe($temporaryTargetEditionId);

        expect((int) $temporaryLog['catalog_edition_id'])
            ->not->toBe($this->sourceEdition->id);

        expect((int) $temporaryLog['stamp_id'])
            ->toBe((int) $temporaryTargetEntry['stamp_id']);

        expect($temporaryLog['stamp_series_id'])->toBeNull();
        expect($temporaryLog['action'])->toBe('created');
        expect($temporaryLog['old_values'])->toBeNull();
        expect($temporaryLog['new_values'])->toBeString();
        expect($temporaryLog['created_at'])->not->toBeNull();
        expect($temporaryLog['updated_at'])->not->toBeNull();
    }

    // Controleer de specifieke eigenschappen van de catalogusvermelding.
    expect($temporaryEntryLog['record_type'])
        ->toBe('stamp_catalog_entry');

    expect((int) $temporaryEntryLog['record_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect((int) $temporaryEntryLog['record_id'])
        ->not->toBe($matchingSourceEntry->id);

    expect($temporaryEntryLog['condition_id'])->toBeNull();

    // Decodeer de nieuwe waarden van de catalogusvermelding.
    $temporaryEntryNewValues = json_decode(
        $temporaryEntryLog['new_values'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Controleer de volledige geregistreerde catalogusvermelding.
    expect($temporaryEntryNewValues)->toBeArray();

    expect($temporaryEntryNewValues)
        ->toEqual($temporaryTargetEntry);

    // Controleer de specifieke eigenschappen van de cataloguswaarde.
    expect($temporaryValueLog['record_type'])
        ->toBe('stamp_catalog_value');

    expect((int) $temporaryValueLog['record_id'])
        ->toBe($temporaryTargetValue['id']);

    expect((int) $temporaryValueLog['record_id'])
        ->not->toBe($matchingSourceValue->id);

    expect((int) $temporaryValueLog['condition_id'])
        ->toBe((int) $temporaryTargetValue['condition_id']);

    expect((int) $temporaryValueLog['condition_id'])
        ->toBe((int) $matchingSourceValue->condition_id);

    // Decodeer de nieuwe waarden van de cataloguswaarde.
    $temporaryValueNewValues = json_decode(
        $temporaryValueLog['new_values'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($temporaryValueNewValues)->toBeArray();

    // Controleer de geregistreerde identificatie en koppelingen.
    expect((int) $temporaryValueNewValues['id'])
        ->toBe($temporaryTargetValue['id']);

    expect((int) $temporaryValueNewValues['stamp_catalog_entry_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect((int) $temporaryValueNewValues['condition_id'])
        ->toBe((int) $matchingSourceValue->condition_id);

    // Controleer het geregistreerde catalogusbedrag.
    expect((float) $temporaryValueNewValues['catalog_value'])
        ->toBe((float) $matchingSourceValue->catalog_value);

    // Controleer de geregistreerde valuta en opmerkingen.
    expect($temporaryValueNewValues['currency'])
        ->toBe($matchingSourceValue->currency);

    expect($temporaryValueNewValues['notes'])
        ->toBe($matchingSourceValue->notes);

    // Controleer de geregistreerde controlemarkering.
    expect(in_array(
        $temporaryValueNewValues['needs_review'],
        [true, 1],
        true
    ))->toBeTrue();

    // Normaliseer uitsluitend de representatie van bedrag en controlemarkering.
    $normalizedLoggedValue = $temporaryValueNewValues;
    $normalizedTargetValue = $temporaryTargetValue;

    $normalizedLoggedValue['catalog_value'] =
        (float) $normalizedLoggedValue['catalog_value'];

    $normalizedTargetValue['catalog_value'] =
        (float) $normalizedTargetValue['catalog_value'];

    $normalizedLoggedValue['needs_review'] =
        (bool) $normalizedLoggedValue['needs_review'];

    $normalizedTargetValue['needs_review'] =
        (bool) $normalizedTargetValue['needs_review'];

    // Controleer dat het technische Eloquent-hulpattribuut
    // niet in de geschiedenisregistratie is opgenomen.
    expect(array_key_exists(
        'laravel_through_key',
        $normalizedLoggedValue
    ))->toBeFalse();

    // Verwijder uitsluitend het technische Eloquent-hulpattribuut
    // uit de opgehaalde doelwaarde.
    unset($normalizedTargetValue['laravel_through_key']);

    // Controleer de volledige geregistreerde cataloguswaarde.
    expect($normalizedLoggedValue)
        ->toEqual($normalizedTargetValue);

    // Controleer dat voor de mislukte tweede doelwaarde geen log bestaat.
    expect(
        $temporaryLogs
            ->where('record_type', 'stamp_catalog_value')
            ->where('condition_id', $failingValue['condition_id'])
    )->toBeEmpty();

    // Controleer dat de oorspronkelijke gebruiker nog steeds is ingelogd.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat alle oorspronkelijke databaseaantallen zijn hersteld.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer dat de oorspronkelijke broneditie nog bestaat.
    expect(
        CatalogEdition::query()
            ->whereKey($this->sourceEdition->id)
            ->exists()
    )->toBeTrue();

    // Controleer de oorspronkelijke catalogusvermeldingen en cataloguswaarden.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer dat iedere oorspronkelijke catalogusvermelding
    // nog steeds precies twee cataloguswaarden heeft.
    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);
    expect($sourceEntryThree->values()->count())->toBe(2);

    // Controleer dat de tijdelijk aangemaakte doeleditie is teruggedraaid.
    expect(
        CatalogEdition::query()
            ->whereKey($temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de doeleditie ook niet meer bestaat op basis van naam en jaar.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijk aangemaakte doelvermelding is teruggedraaid.
    expect(
        StampCatalogEntry::query()
            ->whereKey($temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen catalogusvermeldingen van de doeleditie zijn achtergebleven.
    expect(
        StampCatalogEntry::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijk opgeslagen doelwaarde is teruggedraaid.
    expect(
        StampCatalogValue::query()
            ->whereKey($temporaryTargetValue['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen cataloguswaarden van de doelvermelding zijn achtergebleven.
    expect(
        StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat de tijdelijk aangemaakte geschiedenisbatch is teruggedraaid.
    expect(
        CatalogChangeBatch::query()
            ->whereKey($temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de wijzigingslog van de doelvermelding is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($temporaryEntryLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de wijzigingslog van de doelwaarde is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($temporaryValueLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen geschiedenisregistratie is achtergebleven.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat geen geschiedenisbatch aan de mislukte doeleditie is gekoppeld.
    expect(
        CatalogChangeBatch::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat geen wijzigingslogs aan de mislukte doeleditie zijn gekoppeld.
    expect(
        CatalogChangeLog::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat geen wijzigingslogs van de tijdelijke batch zijn achtergebleven.
    expect(
        CatalogChangeLog::query()
            ->where('catalog_change_batch_id', $temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de actieve geschiedenisbatch volledig is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer dat de gebruikte duplicatiebevestiging is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat geen succesmelding is geregistreerd.
    expect(session()->has('success'))->toBeFalse();

    // Controleer nogmaals dat de oorspronkelijke eventdispatcher actief is.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);
});

test('een fout tijdens de geschiedenisregistratie van een duplicatie vanuit Livewire draait de volledige cataloguseditie en geschiedenisregistratie terug', function () {
    // Meld de gewone gebruiker aan.
    $this->actingAs($this->user);

    // Maak de eerste oorspronkelijke postzegel aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    // Maak de tweede oorspronkelijke postzegel aan.
    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    // Maak de derde oorspronkelijke postzegel aan.
    $stampThree = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak de eerste oorspronkelijke catalogusvermelding aan.
    $sourceEntryOne = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'LW-031',
        'notes' => 'Eerste catalogusvermelding voor de geschiedenisrollbacktest.',
    ]);

    // Maak de tweede oorspronkelijke catalogusvermelding aan.
    $sourceEntryTwo = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'LW-032',
        'notes' => 'Tweede catalogusvermelding voor de geschiedenisrollbacktest.',
    ]);

    // Maak de derde oorspronkelijke catalogusvermelding aan.
    $sourceEntryThree = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stampThree->id,
        'catalog_number' => 'LW-033',
        'notes' => 'Derde catalogusvermelding voor de geschiedenisrollbacktest.',
    ]);

    // Maak de oorspronkelijke postfrisse waarde van de eerste postzegel aan.
    $sourceValueOne = $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke gestempelde waarde van de eerste postzegel aan.
    $sourceValueTwo = $sourceEntryOne->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '5.25',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke postfrisse waarde van de tweede postzegel aan.
    $sourceValueThree = $sourceEntryTwo->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '22.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de tweede postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke gestempelde waarde van de tweede postzegel aan.
    $sourceValueFour = $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke postfrisse waarde van de derde postzegel aan.
    $sourceValueFive = $sourceEntryThree->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '35.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de derde postzegel.',
        'needs_review' => true,
    ]);

    // Maak de oorspronkelijke gestempelde waarde van de derde postzegel aan.
    $sourceValueSix = $sourceEntryThree->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '8.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de derde postzegel.',
        'needs_review' => false,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampOne,
        $stampTwo,
        $stampThree,
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $sourceValueFive,
        $sourceValueSix,
        $this->admin,
        $this->user,
    ];

    // Leg de volledige oorspronkelijke databaseattributen vast.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de ingelogde gebruiker en diens gebruikersrechten.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer het aantal oorspronkelijke modellen en snapshots.
    expect($originalModels)->toHaveCount(19);
    expect($originalSnapshots)->toHaveCount(19);

    // Controleer de oorspronkelijke databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer de oorspronkelijke gegevens van de broneditie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer het aantal waarden per oorspronkelijke catalogusvermelding.
    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);
    expect($sourceEntryThree->values()->count())->toBe(2);

    // Controleer dat de toekomstige doeleditie nog niet bestaat.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat nog geen geschiedenisregistratie bestaat.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat nog geen actieve geschiedenisbatch bestaat.
    expect($this->history->getActiveBatch())->toBeNull();

    // Bereid het Livewire-duplicatieformulier voor.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Rollbacktest tijdens de geschiedenisregistratie van een duplicatie.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Controleer de geselecteerde broneditie en duplicatieoptie.
    $component
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    // Bepaal de sessiesleutel van de duplicatiebevestiging.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat nog geen duplicatiebevestiging bestaat.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor zonder deze daadwerkelijk uit te voeren.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatiebevestiging is opgeslagen.
    expect(session()->has($sessionKey))->toBeTrue();

    // Leg de oorspronkelijke bevestigingshash vast.
    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();

    expect($confirmationHash)
        ->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat de oorspronkelijke gebruiker nog steeds is ingelogd.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat alle oorspronkelijke databaseaantallen ongewijzigd zijn.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer dat de oorspronkelijke broneditie ongewijzigd is.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer dat de toekomstige doeleditie nog niet is aangemaakt.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat nog geen geschiedenisregistratie heeft plaatsgevonden.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat nog geen actieve geschiedenisbatch bestaat.
    expect($this->history->getActiveBatch())->toBeNull();

    // Leg de oorspronkelijke Eloquent-eventdispatcher vast.
    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    // Controleer dat een oorspronkelijke eventdispatcher beschikbaar is.
    expect($originalDispatcher)->not->toBeNull();

    // Maak een tijdelijke kopie van de oorspronkelijke eventdispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Controleer dat de tijdelijke eventdispatcher een afzonderlijk object is.
    expect($temporaryDispatcher)->not->toBe($originalDispatcher);

    // Initialiseer de teller voor aangemaakte wijzigingslogs van doelwaarden.
    $targetValueLogCreationAttempts = 0;

    // Initialiseer de tijdelijke databasestatus vóór de geforceerde fout.
    $stateBeforeFailure = [];

    // Controleer de oorspronkelijke toestand van de teller.
    expect($targetValueLogCreationAttempts)->toBe(0);

    // Controleer dat nog geen tijdelijke databasestatus is vastgelegd.
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke eventdispatcher nog steeds actief is.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Registreer een tijdelijke eventlistener voor aangemaakte wijzigingslogs.
    $temporaryDispatcher->listen(
        'eloquent.created: '.CatalogChangeLog::class,
        function (CatalogChangeLog $log) use (
            &$targetValueLogCreationAttempts,
            &$stateBeforeFailure
        ) {
            // Laat wijzigingslogs van andere recordtypen ongemoeid.
            if ($log->record_type !== 'stamp_catalog_value') {
                return;
            }

            // Laat wijzigingslogs van andere acties ongemoeid.
            if ($log->action !== 'created') {
                return;
            }

            // Laat eventuele wijzigingslogs van de broneditie ongemoeid.
            if ((int) $log->catalog_edition_id === (int) $this->sourceEdition->id) {
                return;
            }

            // Verhoog de teller voor aangemaakte wijzigingslogs van doelwaarden.
            $targetValueLogCreationAttempts++;

            // Veroorzaak uitsluitend een fout bij de eerste wijzigingslog
            // van een gedupliceerde cataloguswaarde.
            if ($targetValueLogCreationAttempts !== 1) {
                return;
            }

            // Haal de tijdelijk aangemaakte doeleditie op.
            $targetEdition = CatalogEdition::query()
                ->findOrFail($log->catalog_edition_id);

            // Haal de daadwerkelijk opgeslagen doelwaarde op.
            $targetValue = StampCatalogValue::query()
                ->findOrFail($log->record_id);

            // Haal de bijbehorende doelvermelding op.
            $targetEntry = StampCatalogEntry::query()
                ->findOrFail($targetValue->stamp_catalog_entry_id);

            // Haal de daadwerkelijk opgeslagen wijzigingslog opnieuw op.
            $persistedLog = CatalogChangeLog::query()
                ->findOrFail($log->id);

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = app(CatalogChangeHistoryService::class)
                ->getActiveBatch();

            // Leg de volledige tijdelijke databasestatus vast.
            $stateBeforeFailure = [
                'catalog_count' => Catalog::count(),
                'edition_count' => CatalogEdition::count(),
                'stamp_count' => Stamp::count(),
                'entry_count' => StampCatalogEntry::count(),
                'value_count' => StampCatalogValue::count(),

                'target_edition_id' => $targetEdition->id,
                'target_edition_attributes' => $targetEdition->getAttributes(),

                'target_entry_count' => $targetEdition->stampEntries()->count(),

                'target_entries' => $targetEdition->stampEntries()
                    ->get()
                    ->map(
                        fn (StampCatalogEntry $entry) => $entry->getAttributes()
                    )
                    ->all(),

                'target_value_count' => $targetEdition->stampValues()->count(),

                'target_values' => $targetEdition->stampValues()
                    ->get()
                    ->map(
                        fn (StampCatalogValue $value) => $value->getAttributes()
                    )
                    ->all(),

                'source_entry_count' => $this->sourceEdition
                    ->stampEntries()
                    ->count(),

                'source_value_count' => $this->sourceEdition
                    ->stampValues()
                    ->count(),

                'batch_count' => CatalogChangeBatch::count(),
                'log_count' => CatalogChangeLog::count(),

                'active_batch_id' => $activeBatch?->id,
                'active_batch_attributes' => $activeBatch?->getAttributes(),

                'active_batch_logs' => $activeBatch?->logs()
                    ->get()
                    ->map(
                        fn (CatalogChangeLog $batchLog) => $batchLog->getAttributes()
                    )
                    ->all(),

                'triggering_log_id' => $log->id,
                'triggering_log_attributes' => $log->getAttributes(),

                'persisted_log_attributes' => $persistedLog->getAttributes(),

                'triggering_target_entry_attributes' => $targetEntry->getAttributes(),

                'triggering_target_value_attributes' => $targetValue->getAttributes(),
            ];

            // Veroorzaak een fout nadat de wijzigingslog daadwerkelijk
            // in de database is opgeslagen.
            throw new RuntimeException(
                'Geforceerde fout na het opslaan van de eerste wijzigingslog van een gedupliceerde cataloguswaarde vanuit Livewire.'
            );
        }
    );

    // Controleer dat de tijdelijke eventlistener nog niet is uitgevoerd.
    expect($targetValueLogCreationAttempts)->toBe(0);

    // Controleer dat nog geen tijdelijke databasestatus is vastgelegd.
    expect($stateBeforeFailure)->toBeEmpty();

    // Controleer dat de oorspronkelijke eventdispatcher nog steeds actief is.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat de oorspronkelijke duplicatiebevestiging nog beschikbaar is.
    expect(session()->has($sessionKey))->toBeTrue();

    // Controleer dat de oorspronkelijke bevestigingshash ongewijzigd is.
    expect(session()->get($sessionKey))->toBe($confirmationHash);

    // Verplaats de testtijd zodat tijdelijke timestamps verschillen
    // van de oorspronkelijke timestamps.
    $this->travel(1)->minute();

    try {
        // Activeer de tijdelijke Eloquent-eventdispatcher.
        CatalogChangeLog::setEventDispatcher($temporaryDispatcher);

        // Controleer dat de tijdelijke eventdispatcher actief is.
        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($temporaryDispatcher);

        // Schakel de standaard exception handling tijdelijk uit.
        $this->withoutExceptionHandling();

        // Bevestig de duplicatie en controleer de geforceerde uitzondering.
        expect(fn () => $component->call('confirmSave'))
            ->toThrow(
                RuntimeException::class,
                'Geforceerde fout na het opslaan van de eerste wijzigingslog van een gedupliceerde cataloguswaarde vanuit Livewire.'
            );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        CatalogChangeLog::setEventDispatcher($originalDispatcher);

        // Herstel de standaard exception handling.
        $this->withExceptionHandling();

        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer dat de oorspronkelijke eventdispatcher is hersteld.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer dat precies één wijzigingslog van een doelwaarde
    // is aangemaakt voordat de geforceerde fout optrad.
    expect($targetValueLogCreationAttempts)->toBe(1);

    // Controleer dat de tijdelijke databasestatus daadwerkelijk is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer de tijdelijke databaseaantallen vóór de geforceerde fout.
    expect($stateBeforeFailure['catalog_count'])->toBe(1);
    expect($stateBeforeFailure['edition_count'])->toBe(2);
    expect($stateBeforeFailure['stamp_count'])->toBe(3);
    expect($stateBeforeFailure['entry_count'])->toBe(4);
    expect($stateBeforeFailure['value_count'])->toBe(7);

    // Controleer de oorspronkelijke gegevens van de broneditie.
    expect($stateBeforeFailure['source_entry_count'])->toBe(3);
    expect($stateBeforeFailure['source_value_count'])->toBe(6);

    // Controleer de tijdelijk aangemaakte doelgegevens.
    expect($stateBeforeFailure['target_entry_count'])->toBe(1);
    expect($stateBeforeFailure['target_value_count'])->toBe(1);

    expect($stateBeforeFailure['target_entries'])->toHaveCount(1);
    expect($stateBeforeFailure['target_values'])->toHaveCount(1);

    // Leg het ID van de tijdelijk aangemaakte doeleditie vast.
    $temporaryTargetEditionId = $stateBeforeFailure['target_edition_id'];

    expect($temporaryTargetEditionId)->toBeInt();

    expect($temporaryTargetEditionId)
        ->not->toBe($this->sourceEdition->id);

    // Leg de volledige attributen van de tijdelijke doeleditie vast.
    $temporaryTargetEdition = $stateBeforeFailure['target_edition_attributes'];

    expect($temporaryTargetEdition)->toBeArray();

    // Controleer de gegevens van de tijdelijk aangemaakte doeleditie.
    expect($temporaryTargetEdition['id'])
        ->toBe($temporaryTargetEditionId);

    expect($temporaryTargetEdition['catalog_id'])
        ->toBe($this->catalog->id);

    expect($temporaryTargetEdition['edition_name'])
        ->toBe('Editie 2027');

    expect($temporaryTargetEdition['edition_year'])
        ->toBe(2027);

    expect(
        Carbon::parse(
            $temporaryTargetEdition['publication_date']
        )->toDateString()
    )->toBe('2027-01-15');

    expect($temporaryTargetEdition['currency'])
        ->toBe('EUR');

    expect($temporaryTargetEdition['notes'])
        ->toBe('Rollbacktest tijdens de geschiedenisregistratie van een duplicatie.');

    expect($temporaryTargetEdition['created_at'])->not->toBeNull();
    expect($temporaryTargetEdition['updated_at'])->not->toBeNull();

    // Leg de tijdelijk aangemaakte doelvermelding vast.
    $temporaryTargetEntry = $stateBeforeFailure['target_entries'][0];

    expect($temporaryTargetEntry)->toBeArray();
    expect($temporaryTargetEntry['id'])->toBeInt();

    expect($temporaryTargetEntry['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    // Zoek de bijbehorende oorspronkelijke catalogusvermelding.
    $matchingSourceEntry = collect([
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceEntryThree,
    ])->first(
        fn (StampCatalogEntry $sourceEntry) => (int) $sourceEntry->stamp_id ===
            (int) $temporaryTargetEntry['stamp_id']
    );

    expect($matchingSourceEntry)
        ->toBeInstanceOf(StampCatalogEntry::class);

    // Controleer dat een afzonderlijke doelvermelding is aangemaakt.
    expect($temporaryTargetEntry['id'])
        ->not->toBe($matchingSourceEntry->id);

    // Controleer dat de oorspronkelijke vermeldingsgegevens correct zijn gekopieerd.
    expect($temporaryTargetEntry['stamp_id'])
        ->toBe($matchingSourceEntry->stamp_id);

    expect($temporaryTargetEntry['catalog_number'])
        ->toBe($matchingSourceEntry->catalog_number);

    expect($temporaryTargetEntry['notes'])
        ->toBe($matchingSourceEntry->notes);

    // Controleer dat de rechtstreeks opgehaalde doelvermelding overeenkomt.
    expect($stateBeforeFailure['triggering_target_entry_attributes'])
        ->toEqual($temporaryTargetEntry);

    // Leg de rechtstreeks uit de database opgehaalde doelwaarde vast.
    $temporaryTargetValue = $stateBeforeFailure['triggering_target_value_attributes'];

    expect($temporaryTargetValue)->toBeArray();
    expect($temporaryTargetValue['id'])->toBeInt();

    expect($temporaryTargetValue['stamp_catalog_entry_id'])
        ->toBe($temporaryTargetEntry['id']);

    // Zoek de bijbehorende oorspronkelijke cataloguswaarde.
    $matchingSourceValue = collect([
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $sourceValueFive,
        $sourceValueSix,
    ])->first(
        fn (StampCatalogValue $sourceValue) => (int) $sourceValue->stamp_catalog_entry_id ===
            (int) $matchingSourceEntry->id
            && (int) $sourceValue->condition_id ===
            (int) $temporaryTargetValue['condition_id']
    );

    expect($matchingSourceValue)
        ->toBeInstanceOf(StampCatalogValue::class);

    // Controleer dat een afzonderlijke doelwaarde is aangemaakt.
    expect($temporaryTargetValue['id'])
        ->not->toBe($matchingSourceValue->id);

    // Controleer dat de oorspronkelijke waarderingsgegevens correct zijn gekopieerd.
    expect($temporaryTargetValue['condition_id'])
        ->toBe($matchingSourceValue->condition_id);

    expect((float) $temporaryTargetValue['catalog_value'])
        ->toBe((float) $matchingSourceValue->catalog_value);

    expect($temporaryTargetValue['currency'])
        ->toBe($matchingSourceValue->currency);

    expect($temporaryTargetValue['notes'])
        ->toBe($matchingSourceValue->notes);

    // Controleer dat de gedupliceerde cataloguswaarde moet worden gecontroleerd.
    expect(
        in_array($temporaryTargetValue['needs_review'], [true, 1], true)
    )->toBeTrue();

    // Controleer de timestamps van de tijdelijk opgeslagen doelwaarde.
    expect($temporaryTargetValue['created_at'])->not->toBeNull();
    expect($temporaryTargetValue['updated_at'])->not->toBeNull();

    // Controleer dat de rechtstreeks opgehaalde doelwaarde geen
    // technisch Eloquent-hulpattribuut bevat.
    expect(
        array_key_exists('laravel_through_key', $temporaryTargetValue)
    )->toBeFalse();

    // Leg de via de HasManyThrough-relatie opgehaalde doelwaarde vast.
    $relatedTargetValue = $stateBeforeFailure['target_values'][0];

    // Verwijder uitsluitend het technische Eloquent-hulpattribuut.
    unset($relatedTargetValue['laravel_through_key']);

    // Controleer dat beide opgehaalde doelwaarden volledig overeenkomen.
    expect($relatedTargetValue)
        ->toEqual($temporaryTargetValue);

    // Controleer de tijdelijke geschiedenisregistratie.
    expect($stateBeforeFailure['batch_count'])->toBe(1);
    expect($stateBeforeFailure['log_count'])->toBe(2);

    expect($stateBeforeFailure['active_batch_id'])->toBeInt();

    expect($stateBeforeFailure['active_batch_attributes'])
        ->toBeArray();

    expect($stateBeforeFailure['active_batch_logs'])
        ->toHaveCount(2);

    // Controleer dat de wijzigingslog die de fout veroorzaakt
    // daadwerkelijk een database-ID heeft.
    expect($stateBeforeFailure['triggering_log_id'])
        ->toBeInt();

    expect($stateBeforeFailure['triggering_log_attributes'])
        ->toBeArray();

    expect($stateBeforeFailure['persisted_log_attributes'])
        ->toBeArray();

    // Controleer dat de opnieuw opgehaalde wijzigingslog
    // hetzelfde database-ID heeft.
    expect($stateBeforeFailure['persisted_log_attributes']['id'])
        ->toBe($stateBeforeFailure['triggering_log_id']);

    // Leg de tijdelijk aangemaakte geschiedenisbatch vast.
    $temporaryBatch = $stateBeforeFailure['active_batch_attributes'];

    expect($temporaryBatch)->toBeArray();
    expect($temporaryBatch['id'])->toBeInt();

    // Controleer dat de geschiedenisbatch overeenkomt met de actieve batch.
    expect($temporaryBatch['id'])
        ->toBe($stateBeforeFailure['active_batch_id']);

    // Controleer dat de geschiedenisbatch uitsluitend aan de doeleditie is gekoppeld.
    expect($temporaryBatch['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect($temporaryBatch['catalog_edition_id'])
        ->not->toBe($this->sourceEdition->id);

    // Controleer de geregistreerde gebruiker.
    expect($temporaryBatch['user_id'])
        ->toBe($this->user->id);

    expect($temporaryBatch['user_name'])
        ->toBe($this->user->name);

    // Controleer het geregistreerde bewerkingstype.
    expect($temporaryBatch['operation'])
        ->toBe('duplicate');

    // Controleer de geregistreerde omschrijving.
    expect($temporaryBatch['description'])
        ->toBe(
            'Duplicatie van catalogusvermeldingen vanuit '
            .$this->sourceEdition->edition_name
        );

    // Controleer de historische catalogusnaam en editienaam.
    expect($temporaryBatch['catalog_name'])
        ->toBe($this->catalog->name);

    expect($temporaryBatch['catalog_edition_name'])
        ->toBe('Editie 2027');

    // Controleer de timestamps van de geschiedenisbatch.
    expect($temporaryBatch['created_at'])->not->toBeNull();
    expect($temporaryBatch['updated_at'])->not->toBeNull();

    // Verzamel de tijdelijk opgeslagen wijzigingslogs.
    $temporaryLogs = collect($stateBeforeFailure['active_batch_logs']);

    expect($temporaryLogs)->toHaveCount(2);

    // Controleer dat precies één wijzigingslog per recordtype bestaat.
    expect(
        $temporaryLogs
            ->where('record_type', 'stamp_catalog_entry')
            ->count()
    )->toBe(1);

    expect(
        $temporaryLogs
            ->where('record_type', 'stamp_catalog_value')
            ->count()
    )->toBe(1);

    // Leg beide tijdelijke wijzigingslogs afzonderlijk vast.
    $temporaryEntryLog = $temporaryLogs
        ->firstWhere('record_type', 'stamp_catalog_entry');

    $temporaryValueLog = $temporaryLogs
        ->firstWhere('record_type', 'stamp_catalog_value');

    expect($temporaryEntryLog)->toBeArray();
    expect($temporaryValueLog)->toBeArray();

    // Controleer dat beide wijzigingslogs afzonderlijke database-ID's hebben.
    expect($temporaryEntryLog['id'])->toBeInt();
    expect($temporaryValueLog['id'])->toBeInt();

    expect($temporaryEntryLog['id'])
        ->not->toBe($temporaryValueLog['id']);

    // Controleer de gemeenschappelijke attributen van beide wijzigingslogs.
    foreach ($temporaryLogs as $temporaryLog) {
        expect($temporaryLog['catalog_change_batch_id'])
            ->toBe($temporaryBatch['id']);

        expect($temporaryLog['catalog_edition_id'])
            ->toBe($temporaryTargetEditionId);

        expect($temporaryLog['catalog_edition_id'])
            ->not->toBe($this->sourceEdition->id);

        expect($temporaryLog['stamp_id'])
            ->toBe($temporaryTargetEntry['stamp_id']);

        expect($temporaryLog['stamp_series_id'])
            ->toBeNull();

        expect($temporaryLog['action'])
            ->toBe('created');

        expect($temporaryLog['old_values'])
            ->toBeNull();

        expect($temporaryLog['new_values'])
            ->toBeString();

        expect($temporaryLog['created_at'])
            ->not->toBeNull();

        expect($temporaryLog['updated_at'])
            ->not->toBeNull();
    }

    // Controleer de specifieke attributen van de vermeldingswijzigingslog.
    expect($temporaryEntryLog['record_type'])
        ->toBe('stamp_catalog_entry');

    expect($temporaryEntryLog['record_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect($temporaryEntryLog['record_id'])
        ->not->toBe($matchingSourceEntry->id);

    expect($temporaryEntryLog['condition_id'])
        ->toBeNull();

    // Decodeer de geregistreerde attributen van de doelvermelding.
    $temporaryEntryNewValues = json_decode(
        $temporaryEntryLog['new_values'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($temporaryEntryNewValues)->toBeArray();

    // Controleer de volledige geregistreerde doelvermelding.
    expect($temporaryEntryNewValues)
        ->toEqual($temporaryTargetEntry);

    // Controleer de specifieke attributen van de waardewijzigingslog.
    expect($temporaryValueLog['record_type'])
        ->toBe('stamp_catalog_value');

    expect($temporaryValueLog['record_id'])
        ->toBe($temporaryTargetValue['id']);

    expect($temporaryValueLog['record_id'])
        ->not->toBe($matchingSourceValue->id);

    expect($temporaryValueLog['condition_id'])
        ->toBe($temporaryTargetValue['condition_id']);

    expect($temporaryValueLog['condition_id'])
        ->toBe($matchingSourceValue->condition_id);

    // Decodeer de geregistreerde attributen van de doelwaarde.
    $temporaryValueNewValues = json_decode(
        $temporaryValueLog['new_values'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($temporaryValueNewValues)->toBeArray();

    // Controleer de geregistreerde identificatiegegevens.
    expect($temporaryValueNewValues['id'])
        ->toBe($temporaryTargetValue['id']);

    expect($temporaryValueNewValues['stamp_catalog_entry_id'])
        ->toBe($temporaryTargetEntry['id']);

    expect($temporaryValueNewValues['condition_id'])
        ->toBe($temporaryTargetValue['condition_id']);

    // Controleer de geregistreerde waarderingsgegevens.
    expect((float) $temporaryValueNewValues['catalog_value'])
        ->toBe((float) $temporaryTargetValue['catalog_value']);

    expect($temporaryValueNewValues['currency'])
        ->toBe($temporaryTargetValue['currency']);

    expect($temporaryValueNewValues['notes'])
        ->toBe($temporaryTargetValue['notes']);

    // Controleer de geregistreerde controlemarkering.
    expect(
        in_array(
            $temporaryValueNewValues['needs_review'],
            [true, 1],
            true
        )
    )->toBeTrue();

    // Maak afzonderlijke kopieën voor de volledige attribuutvergelijking.
    $normalizedLoggedValue = $temporaryValueNewValues;
    $normalizedTargetValue = $temporaryTargetValue;

    // Normaliseer uitsluitend de bekende verschillen in gegevenstypen.
    $normalizedLoggedValue['catalog_value'] =
        (float) $normalizedLoggedValue['catalog_value'];

    $normalizedTargetValue['catalog_value'] =
        (float) $normalizedTargetValue['catalog_value'];

    $normalizedLoggedValue['needs_review'] =
        (bool) $normalizedLoggedValue['needs_review'];

    $normalizedTargetValue['needs_review'] =
        (bool) $normalizedTargetValue['needs_review'];

    // Controleer dat het technische Eloquent-hulpattribuut
    // niet in de geschiedenisregistratie voorkomt.
    expect(
        array_key_exists(
            'laravel_through_key',
            $normalizedLoggedValue
        )
    )->toBeFalse();

    // Controleer dat ook de rechtstreeks opgehaalde doelwaarde
    // dit technische hulpattribuut niet bevat.
    expect(
        array_key_exists(
            'laravel_through_key',
            $normalizedTargetValue
        )
    )->toBeFalse();

    // Controleer de volledige geregistreerde doelwaarde.
    expect($normalizedLoggedValue)
        ->toEqual($normalizedTargetValue);

    // Leg de wijzigingslog vast die de geforceerde uitzondering veroorzaakte.
    $triggeringLog = $stateBeforeFailure['triggering_log_attributes'];

    // Leg de rechtstreeks uit de database opgehaalde wijzigingslog vast.
    $persistedLog = $stateBeforeFailure['persisted_log_attributes'];

    expect($triggeringLog)->toBeArray();
    expect($persistedLog)->toBeArray();

    // Controleer dat de wijzigingslog van de doelwaarde
    // daadwerkelijk de geforceerde uitzondering veroorzaakte.
    expect($stateBeforeFailure['triggering_log_id'])
        ->toBe($temporaryValueLog['id']);

    expect($triggeringLog['id'])
        ->toBe($temporaryValueLog['id']);

    expect($persistedLog['id'])
        ->toBe($temporaryValueLog['id']);

    // Controleer dat niet de vermeldingswijzigingslog de fout veroorzaakte.
    expect($triggeringLog['id'])
        ->not->toBe($temporaryEntryLog['id']);

    // Controleer dat de opnieuw opgehaalde wijzigingslog volledig
    // overeenkomt met de wijzigingslog uit de actieve geschiedenisbatch.
    expect($persistedLog)
        ->toEqual($temporaryValueLog);

    // Controleer dat ook het model uit het created-event volledig
    // overeenkomt met de daadwerkelijk opgeslagen wijzigingslog.
    expect($triggeringLog)
        ->toEqual($persistedLog);

    // Controleer dat de oorspronkelijke gebruiker nog steeds is ingelogd.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat alle oorspronkelijke databaseaantallen zijn hersteld.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(3);
    expect(StampCatalogEntry::count())->toBe(3);
    expect(StampCatalogValue::count())->toBe(6);

    // Controleer dat de oorspronkelijke broneditie nog bestaat.
    expect(
        CatalogEdition::query()
            ->whereKey($this->sourceEdition->id)
            ->exists()
    )->toBeTrue();

    // Controleer de oorspronkelijke gegevens van de broneditie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3);
    expect($this->sourceEdition->stampValues()->count())->toBe(6);

    // Controleer dat iedere oorspronkelijke catalogusvermelding
    // nog steeds precies twee cataloguswaarden heeft.
    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);
    expect($sourceEntryThree->values()->count())->toBe(2);

    // Controleer dat de tijdelijk aangemaakte doeleditie is teruggedraaid.
    expect(
        CatalogEdition::query()
            ->whereKey($temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de doeleditie ook niet meer bestaat op basis van naam en jaar.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijk aangemaakte doelvermelding is teruggedraaid.
    expect(
        StampCatalogEntry::query()
            ->whereKey($temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen catalogusvermeldingen van de doeleditie zijn achtergebleven.
    expect(
        StampCatalogEntry::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat de tijdelijk opgeslagen doelwaarde is teruggedraaid.
    expect(
        StampCatalogValue::query()
            ->whereKey($temporaryTargetValue['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen cataloguswaarden van de doelvermelding zijn achtergebleven.
    expect(
        StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat de tijdelijk aangemaakte geschiedenisbatch is teruggedraaid.
    expect(
        CatalogChangeBatch::query()
            ->whereKey($temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de wijzigingslog van de doelvermelding is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($temporaryEntryLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de wijzigingslog van de doelwaarde is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($temporaryValueLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer specifiek dat de daadwerkelijk opgeslagen wijzigingslog
    // die de geforceerde uitzondering veroorzaakte, niet meer bestaat.
    expect(
        CatalogChangeLog::query()
            ->whereKey($stateBeforeFailure['triggering_log_id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat ook de rechtstreeks uit de database opgehaalde
    // wijzigingslog volledig is teruggedraaid.
    expect(
        CatalogChangeLog::query()
            ->whereKey($persistedLog['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat geen geschiedenisregistratie is achtergebleven.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat geen geschiedenisbatch aan de mislukte doeleditie is gekoppeld.
    expect(
        CatalogChangeBatch::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat geen wijzigingslogs aan de mislukte doeleditie zijn gekoppeld.
    expect(
        CatalogChangeLog::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer dat geen wijzigingslogs van de tijdelijke batch zijn achtergebleven.
    expect(
        CatalogChangeLog::query()
            ->where('catalog_change_batch_id', $temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat de actieve geschiedenisbatch volledig is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer dat de gebruikte duplicatiebevestiging is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer dat geen succesmelding is geregistreerd.
    expect(session()->has('success'))->toBeFalse();

    // Controleer nogmaals dat de oorspronkelijke eventdispatcher actief is.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);
});

test('de actieve geschiedenisbatch wordt na een succesvolle duplicatie vanuit Livewire volledig opgeruimd', function () {
    // Log in als gewone gebruiker.
    $this->actingAs($this->user);

    // Maak de eerste oorspronkelijke postzegel aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    // Maak de tweede oorspronkelijke postzegel aan.
    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak de eerste oorspronkelijke catalogusvermelding aan.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'LW-034',
        'notes' => 'Eerste catalogusvermelding voor de opruimingstest.',
    ]);

    // Maak de tweede oorspronkelijke catalogusvermelding aan.
    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'LW-035',
        'notes' => 'Tweede catalogusvermelding voor de opruimingstest.',
    ]);

    // Maak de oorspronkelijke postfrisse waarde van de eerste postzegel aan.
    $sourceValueOne = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryOne->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 17.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke gestempelde waarde van de eerste postzegel aan.
    $sourceValueTwo = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryOne->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 6.25,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke postfrisse waarde van de tweede postzegel aan.
    $sourceValueThree = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryTwo->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 27.75,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de tweede postzegel.',
        'needs_review' => true,
    ]);

    // Maak de oorspronkelijke gestempelde waarde van de tweede postzegel aan.
    $sourceValueFour = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryTwo->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => false,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampOne,
        $stampTwo,
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $this->admin,
        $this->user,
    ];

    // Leg de volledige oorspronkelijke databaseattributen vast.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    // Controleer de ingelogde gebruiker.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer het aantal oorspronkelijke modellen en snapshots.
    expect($originalModels)->toHaveCount(15);
    expect($originalSnapshots)->toHaveCount(15);

    // Controleer de oorspronkelijke databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    // Controleer de oorspronkelijke gegevens van de broneditie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2);
    expect($this->sourceEdition->stampValues()->count())->toBe(4);

    // Controleer het aantal waarden per oorspronkelijke catalogusvermelding.
    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);

    // Controleer dat de toekomstige doeleditie nog niet bestaat.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat nog geen geschiedenisregistratie bestaat.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat geen actieve geschiedenisbatch aanwezig is.
    expect($this->history->getActiveBatch())->toBeNull();

    // Controleer dat de container dezelfde geschiedenisservice retourneert.
    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    // Bereid het Livewire-duplicatieformulier voor.
    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Opruimingstest na een succesvolle duplicatie.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    // Controleer de ingestelde duplicatiegegevens.
    $component
        ->assertSet('edition_name', 'Editie 2027')
        ->assertSet('edition_year', 2027)
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    // Stel de sessiesleutel voor de duplicatiebevestiging samen.
    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    // Controleer dat nog geen duplicatiebevestiging is opgeslagen.
    expect(session()->has($sessionKey))->toBeFalse();

    // Bereid de duplicatie voor zonder deze daadwerkelijk uit te voeren.
    $component
        ->call('save')
        ->assertHasNoErrors();

    // Controleer dat de duplicatiebevestiging is opgeslagen.
    expect(session()->has($sessionKey))->toBeTrue();

    // Leg de oorspronkelijke bevestigingshash vast.
    $confirmationHash = session()->get($sessionKey);

    // Controleer de inhoud en het formaat van de bevestigingshash.
    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat de oorspronkelijke gebruiker nog steeds is ingelogd.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de oorspronkelijke databaseaantallen ongewijzigd zijn.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    // Controleer dat de gegevens van de broneditie ongewijzigd zijn.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2);
    expect($this->sourceEdition->stampValues()->count())->toBe(4);

    // Controleer dat de toekomstige doeleditie nog niet bestaat.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen volledig ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat nog geen geschiedenisregistratie is aangemaakt.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat dezelfde geschiedenisservice beschikbaar blijft.
    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    // Controleer dat nog geen actieve geschiedenisbatch aanwezig is.
    expect($this->history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer dat nog geen succesmelding is geregistreerd.
    expect(session()->has('success'))->toBeFalse();

    // ---------------------------------------------------------
    // Tijdelijke Eloquent-eventdispatcher voorbereiden.
    // ---------------------------------------------------------

    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    expect($originalDispatcher)->not->toBeNull();

    $temporaryDispatcher = clone $originalDispatcher;

    expect($temporaryDispatcher)->not->toBe($originalDispatcher);

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    /** @var CatalogChangeBatch|null $capturedBatch */
    $capturedBatch = null;

    $capturedBatchAttributes = [];
    $capturedLogIds = [];
    $capturedLogBatchIds = [];
    $capturedServiceInstances = [];
    $capturedActiveBatchIds = [];
    $createdLogCount = 0;

    expect($capturedHistory)->toBeNull();
    expect($capturedBatch)->toBeNull();
    expect($capturedBatchAttributes)->toBeEmpty();
    expect($capturedLogIds)->toBeEmpty();
    expect($capturedLogBatchIds)->toBeEmpty();
    expect($capturedServiceInstances)->toBeEmpty();
    expect($capturedActiveBatchIds)->toBeEmpty();
    expect($createdLogCount)->toBe(0);

    // ---------------------------------------------------------
    // Actieve geschiedenisbatch en service-instantie vastleggen.
    // ---------------------------------------------------------

    $temporaryDispatcher->listen(
        'eloquent.created: '.CatalogChangeLog::class,
        function (CatalogChangeLog $log) use (
            &$capturedHistory,
            &$capturedBatch,
            &$capturedBatchAttributes,
            &$capturedLogIds,
            &$capturedLogBatchIds,
            &$capturedServiceInstances,
            &$capturedActiveBatchIds,
            &$createdLogCount
        ) {
            // Negeer eventuele wijzigingslogs van de broneditie.
            if ((int) $log->catalog_edition_id === (int) $this->sourceEdition->id) {
                return;
            }

            // Leg de actieve geschiedenisservice vast.
            $history = app(CatalogChangeHistoryService::class);

            // Leg de actieve geschiedenisbatch vast.
            $activeBatch = $history->getActiveBatch();

            $createdLogCount++;

            $capturedHistory = $history;
            $capturedBatch = $activeBatch;

            $capturedBatchAttributes =
                $activeBatch?->getAttributes() ?? [];

            $capturedLogIds[] = $log->id;

            $capturedLogBatchIds[] =
                $log->catalog_change_batch_id;

            $capturedServiceInstances[] = $history;

            $capturedActiveBatchIds[] = $activeBatch?->id;
        }
    );

    // Controleer dat de tijdelijke listener nog niet is uitgevoerd.
    expect($createdLogCount)->toBe(0);
    expect($capturedHistory)->toBeNull();
    expect($capturedBatch)->toBeNull();

    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // ---------------------------------------------------------
    // Succesvolle duplicatie uitvoeren.
    // ---------------------------------------------------------

    expect(session()->has($sessionKey))->toBeTrue();

    expect(session()->get($sessionKey))
        ->toBe($confirmationHash);

    $this->travel(1)->minute();

    try {
        CatalogChangeLog::setEventDispatcher(
            $temporaryDispatcher
        );

        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($temporaryDispatcher);

        $component
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertRedirect(
                route(
                    'backend.catalogs.editions.index',
                    $this->catalog
                )
            );
    } finally {
        CatalogChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->travelBack();
    }

    // Controleer dat de oorspronkelijke dispatcher is hersteld.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // ---------------------------------------------------------
    // Vastgelegde service-instantie en geschiedenisbatch controleren.
    // ---------------------------------------------------------

    expect($createdLogCount)->toBe(6);

    expect($capturedHistory)
        ->toBeInstanceOf(CatalogChangeHistoryService::class);

    expect($capturedBatch)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    expect($capturedHistory)
        ->toBe($this->history);

    expect($capturedHistory)
        ->toBe(app(CatalogChangeHistoryService::class));

    expect($capturedBatchAttributes)->not->toBeEmpty();

    expect($capturedLogIds)->toHaveCount(6);
    expect($capturedLogBatchIds)->toHaveCount(6);
    expect($capturedServiceInstances)->toHaveCount(6);
    expect($capturedActiveBatchIds)->toHaveCount(6);

    // Controleer dat zes afzonderlijke wijzigingslogs zijn aangemaakt.
    expect(array_unique($capturedLogIds))
        ->toHaveCount(6);

    // Controleer dat tijdens iedere geschiedenisregistratie
    // dezelfde geschiedenisservice actief was.
    foreach ($capturedServiceInstances as $serviceInstance) {
        expect($serviceInstance)
            ->toBe($capturedHistory);
    }

    // Controleer dat tijdens iedere geschiedenisregistratie
    // dezelfde geschiedenisbatch actief was.
    foreach ($capturedActiveBatchIds as $activeBatchId) {
        expect($activeBatchId)
            ->toBe($capturedBatch->id);
    }

    // Controleer dat iedere wijzigingslog aan die batch is gekoppeld.
    foreach ($capturedLogBatchIds as $logBatchId) {
        expect($logBatchId)
            ->toBe($capturedBatch->id);
    }

    // ---------------------------------------------------------
    // Opgeslagen doelgegevens controleren.
    // ---------------------------------------------------------

    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(2);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(4);
    expect(StampCatalogValue::count())->toBe(8);

    expect($this->sourceEdition->stampEntries()->count())
        ->toBe(2);

    expect($this->sourceEdition->stampValues()->count())
        ->toBe(4);

    // Haal de succesvol aangemaakte doeleditie op.
    $targetEdition = CatalogEdition::query()
        ->where('catalog_id', $this->catalog->id)
        ->where('edition_name', 'Editie 2027')
        ->where('edition_year', 2027)
        ->sole();

    expect($targetEdition->id)
        ->not->toBe($this->sourceEdition->id);

    expect($targetEdition->catalog_id)
        ->toBe($this->catalog->id);

    expect($targetEdition->edition_name)
        ->toBe('Editie 2027');

    expect($targetEdition->edition_year)
        ->toBe(2027);

    expect(
        Carbon::parse(
            $targetEdition->publication_date
        )->toDateString()
    )->toBe('2027-01-15');

    expect($targetEdition->currency)
        ->toBe('EUR');

    expect($targetEdition->notes)
        ->toBe('Opruimingstest na een succesvolle duplicatie.');

    expect($targetEdition->stampEntries()->count())
        ->toBe(2);

    expect($targetEdition->stampValues()->count())
        ->toBe(4);

    // Controleer alle gedupliceerde catalogusvermeldingen en waarden.
    foreach ([$sourceEntryOne, $sourceEntryTwo] as $sourceEntry) {
        $targetEntry = $targetEdition->stampEntries()
            ->where('stamp_id', $sourceEntry->stamp_id)
            ->sole();

        expect($targetEntry->id)
            ->not->toBe($sourceEntry->id);

        expect($targetEntry->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($targetEntry->stamp_id)
            ->toBe($sourceEntry->stamp_id);

        expect($targetEntry->catalog_number)
            ->toBe($sourceEntry->catalog_number);

        expect($targetEntry->notes)
            ->toBe($sourceEntry->notes);

        expect($targetEntry->values()->count())
            ->toBe(2);

        foreach ($sourceEntry->values()->get() as $sourceValue) {
            $targetValue = $targetEntry->values()
                ->where('condition_id', $sourceValue->condition_id)
                ->sole();

            expect($targetValue->id)
                ->not->toBe($sourceValue->id);

            expect($targetValue->stamp_catalog_entry_id)
                ->toBe($targetEntry->id);

            expect($targetValue->condition_id)
                ->toBe($sourceValue->condition_id);

            expect($targetValue->catalog_value)
                ->toBe($sourceValue->catalog_value);

            expect($targetValue->currency)
                ->toBe($sourceValue->currency);

            expect($targetValue->notes)
                ->toBe($sourceValue->notes);

            expect($targetValue->needs_review)
                ->toBeTrue();
        }
    }

    // ---------------------------------------------------------
    // Opgeslagen geschiedenisregistratie controleren.
    // ---------------------------------------------------------

    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(6);

    $batch = CatalogChangeBatch::query()->sole();

    expect($batch->id)
        ->toBe($capturedBatch->id);

    expect($batch->getAttributes())
        ->toEqual($capturedBatchAttributes);

    expect($batch->catalog_edition_id)
        ->toBe($targetEdition->id);

    expect($batch->user_id)
        ->toBe($this->user->id);

    expect($batch->user_name)
        ->toBe($this->user->name);

    expect($batch->operation)
        ->toBe('duplicate');

    expect($batch->description)
        ->toBe(
            'Duplicatie van catalogusvermeldingen vanuit '
            .$this->sourceEdition->edition_name
        );

    expect($batch->catalog_name)
        ->toBe($this->catalog->name);

    expect($batch->catalog_edition_name)
        ->toBe('Editie 2027');

    expect($batch->logs()->count())
        ->toBe(6);

    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count()
    )->toBe(2);

    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count()
    )->toBe(4);

    expect(
        $batch->logs()
            ->where('action', 'created')
            ->count()
    )->toBe(6);

    // Controleer dat alle vastgelegde wijzigingslogs zijn behouden.
    expect(
        $batch->logs()
            ->pluck('id')
            ->sort()
            ->values()
            ->all()
    )->toEqual(
        collect($capturedLogIds)
            ->sort()
            ->values()
            ->all()
    );

    // Controleer de inhoud van alle wijzigingslogs.
    foreach ($batch->logs()->get() as $log) {
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($targetEdition->id);

        expect($log->catalog_edition_id)
            ->not->toBe($this->sourceEdition->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->action)
            ->toBe('created');

        expect($log->old_values)
            ->toBeNull();

        expect($log->new_values)
            ->toBeArray();

        expect($log->created_at)->not->toBeNull();
        expect($log->updated_at)->not->toBeNull();

        if ($log->record_type === 'stamp_catalog_entry') {
            $loggedEntry = StampCatalogEntry::query()
                ->findOrFail($log->record_id);

            expect($loggedEntry->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($log->stamp_id)
                ->toBe($loggedEntry->stamp_id);

            expect($log->condition_id)
                ->toBeNull();

            expect($log->new_values)
                ->toEqual($loggedEntry->getAttributes());
        } else {
            expect($log->record_type)
                ->toBe('stamp_catalog_value');

            $loggedValue = StampCatalogValue::query()
                ->findOrFail($log->record_id);

            $loggedEntry = StampCatalogEntry::query()
                ->findOrFail(
                    $loggedValue->stamp_catalog_entry_id
                );

            expect($loggedEntry->catalog_edition_id)
                ->toBe($targetEdition->id);

            expect($log->stamp_id)
                ->toBe($loggedEntry->stamp_id);

            expect($log->condition_id)
                ->toBe($loggedValue->condition_id);

            // Normaliseer uitsluitend bekende verschillen
            // in numerieke en booleaanse gegevenstypen.
            $normalizedLoggedValue = $log->new_values;

            $normalizedDatabaseValue =
                $loggedValue->getAttributes();

            $normalizedLoggedValue['catalog_value'] =
                (float) $normalizedLoggedValue['catalog_value'];

            $normalizedDatabaseValue['catalog_value'] =
                (float) $normalizedDatabaseValue['catalog_value'];

            $normalizedLoggedValue['needs_review'] =
                (bool) $normalizedLoggedValue['needs_review'];

            $normalizedDatabaseValue['needs_review'] =
                (bool) $normalizedDatabaseValue['needs_review'];

            expect(
                array_key_exists(
                    'laravel_through_key',
                    $normalizedLoggedValue
                )
            )->toBeFalse();

            expect($normalizedLoggedValue)
                ->toEqual($normalizedDatabaseValue);
        }
    }

    // ---------------------------------------------------------
    // Opruiming van de actieve geschiedenisbatch controleren.
    // ---------------------------------------------------------

    // Controleer specifiek de service die tijdens duplicatie actief was.
    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    // Controleer de oorspronkelijke service-instantie.
    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Controleer de service-instantie uit de container.
    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer dat nog steeds dezelfde service-instantie wordt gebruikt.
    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    // Controleer dat de opgeslagen geschiedenisbatch behouden blijft.
    expect($capturedBatch->fresh())
        ->not->toBeNull();

    expect($capturedBatch->fresh()->getAttributes())
        ->toEqual($capturedBatchAttributes);

    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(6);

    // Controleer dat de bevestigingshash is verwijderd.
    expect(session()->has($sessionKey))->toBeFalse();

    // Controleer de geregistreerde succesmelding.
    expect(session()->has('success'))->toBeTrue();

    expect(session()->get('success'))
        ->toBe(
            __('De cataloguseditie is succesvol toegevoegd.')
        );

    // ---------------------------------------------------------
    // Snapshots vóór een rechtstreekse modelwijziging vastleggen.
    // ---------------------------------------------------------

    $batchSnapshot = $batch->fresh()->getAttributes();

    $logSnapshots = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(
            fn (CatalogChangeLog $log) => $log->getAttributes()
        )
        ->all();

    expect($logSnapshots)->toHaveCount(6);

    // Haal de doelvermelding van de eerste postzegel op.
    $entryToUpdate = $targetEdition->stampEntries()
        ->where('stamp_id', $stampOne->id)
        ->sole();

    // Haal de postfrisse doelwaarde op.
    $valueToUpdate = $entryToUpdate->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    expect($valueToUpdate->catalog_value)
        ->toBe('17.50');

    // Leg alle doelvermeldingen en doelwaarden vast.
    $targetEntrySnapshots = $targetEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->mapWithKeys(
            fn (StampCatalogEntry $entry) => [
                $entry->id => $entry->getAttributes(),
            ]
        )
        ->all();

    $targetValueSnapshots = StampCatalogValue::query()
        ->whereIn(
            'stamp_catalog_entry_id',
            array_keys($targetEntrySnapshots)
        )
        ->orderBy('id')
        ->get()
        ->mapWithKeys(
            fn (StampCatalogValue $value) => [
                $value->id => $value->getAttributes(),
            ]
        )
        ->all();

    expect($targetEntrySnapshots)->toHaveCount(2);
    expect($targetValueSnapshots)->toHaveCount(4);

    // ---------------------------------------------------------
    // Rechtstreekse modelwijziging buiten een actieve batch.
    // ---------------------------------------------------------

    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    $this->travel(2)->minutes();

    try {
        $valueToUpdate->update([
            'catalog_value' => 42.50,
        ]);
    } finally {
        $this->travelBack();
    }

    $updatedValue = $valueToUpdate->fresh();

    expect($updatedValue)->not->toBeNull();

    expect($updatedValue->catalog_value)
        ->toBe('42.50');

    expect($updatedValue->needs_review)
        ->toBeTrue();

    // Controleer dat de oorspronkelijke bronwaarde ongewijzigd is.
    expect($sourceValueOne->fresh()->catalog_value)
        ->toBe('17.50');

    // Controleer dat de gewijzigde doelwaarde een nieuwe timestamp heeft.
    expect($updatedValue->getAttributes()['updated_at'])
        ->not->toBe(
            $targetValueSnapshots[$updatedValue->id]['updated_at']
        );

    // Controleer dat uitsluitend de verwachte attributen zijn gewijzigd.
    $expectedUpdatedAttributes =
        $targetValueSnapshots[$updatedValue->id];

    $expectedUpdatedAttributes['catalog_value'] =
        $updatedValue->getAttributes()['catalog_value'];

    $expectedUpdatedAttributes['updated_at'] =
        $updatedValue->getAttributes()['updated_at'];

    expect($updatedValue->getAttributes())
        ->toEqual($expectedUpdatedAttributes);

    // ---------------------------------------------------------
    // Definitieve gegevens- en geschiedeniscontroles.
    // ---------------------------------------------------------

    // Controleer dat geen nieuwe geschiedenisregistratie is ontstaan.
    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(6);

    expect($batch->logs()->count())
        ->toBe(6);

    // Controleer dat de bestaande geschiedenisbatch ongewijzigd is.
    expect($batch->fresh()->getAttributes())
        ->toEqual($batchSnapshot);

    // Controleer dat alle oorspronkelijke wijzigingslogs ongewijzigd zijn.
    $finalLogSnapshots = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(
            fn (CatalogChangeLog $log) => $log->getAttributes()
        )
        ->all();

    expect($finalLogSnapshots)
        ->toEqual($logSnapshots);

    // Controleer specifiek dat geen update-log voor de doelwaarde bestaat.
    expect(
        CatalogChangeLog::query()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedValue->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeFalse();

    // Controleer dat de oorspronkelijke aanmaaklog behouden is gebleven.
    $originalCreationLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $updatedValue->id)
        ->where('action', 'created')
        ->sole();

    expect(
        (float) $originalCreationLog->new_values['catalog_value']
    )->toBe(17.50);

    expect($updatedValue->catalog_value)
        ->toBe('42.50');

    // Controleer dat alle doelvermeldingen ongewijzigd zijn.
    foreach ($targetEntrySnapshots as $entryId => $snapshot) {
        $freshEntry = StampCatalogEntry::query()
            ->findOrFail($entryId);

        expect($freshEntry->getAttributes())
            ->toEqual($snapshot);
    }

    // Controleer dat alle overige doelwaarden ongewijzigd zijn.
    foreach ($targetValueSnapshots as $valueId => $snapshot) {
        if ((int) $valueId === (int) $updatedValue->id) {
            continue;
        }

        $freshValue = StampCatalogValue::query()
            ->findOrFail($valueId);

        expect($freshValue->getAttributes())
            ->toEqual($snapshot);
    }

    // Controleer dat alle oorspronkelijke modellen ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer de definitieve databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(2);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(4);
    expect(StampCatalogValue::count())->toBe(8);

    expect($this->sourceEdition->stampEntries()->count())
        ->toBe(2);

    expect($this->sourceEdition->stampValues()->count())
        ->toBe(4);

    expect($targetEdition->stampEntries()->count())
        ->toBe(2);

    expect($targetEdition->stampValues()->count())
        ->toBe(4);

    expect(CatalogChangeBatch::count())->toBe(1);
    expect(CatalogChangeLog::count())->toBe(6);

    // Controleer dat geen actieve geschiedenisbatch is achtergebleven.
    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer de identiteit van de geschiedenisservice.
    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    // Controleer dat de oorspronkelijke gebruiker nog is ingelogd.
    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // Controleer dat de oorspronkelijke eventdispatcher actief is.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);
});

test('de actieve geschiedenisbatch wordt na een mislukte duplicatie vanuit Livewire volledig opgeruimd', function () {
    // ---------------------------------------------------------
    // Oorspronkelijke testgegevens voorbereiden.
    // ---------------------------------------------------------

    $this->actingAs($this->user);

    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'LW-036',
        'notes' => 'Eerste catalogusvermelding voor de mislukte opruimingstest.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'LW-037',
        'notes' => 'Tweede catalogusvermelding voor de mislukte opruimingstest.',
    ]);

    $sourceValueOne = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryOne->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 19.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    $sourceValueTwo = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryOne->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 7.25,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde van de eerste postzegel.',
        'needs_review' => false,
    ]);

    $sourceValueThree = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryTwo->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 29.75,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde van de tweede postzegel.',
        'needs_review' => true,
    ]);

    $sourceValueFour = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $sourceEntryTwo->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => false,
    ]);

    // Verzamel alle oorspronkelijke modellen.
    $originalModels = [
        $this->catalog,
        $this->sourceEdition,
        $this->country,
        $this->postfris,
        $this->gestempeld,
        $stampOne,
        $stampTwo,
        $sourceEntryOne,
        $sourceEntryTwo,
        $sourceValueOne,
        $sourceValueTwo,
        $sourceValueThree,
        $sourceValueFour,
        $this->admin,
        $this->user,
    ];

    // Leg alle oorspronkelijke databaseattributen vast.
    $originalSnapshots = [];

    foreach ($originalModels as $index => $model) {
        $originalSnapshots[$index] = $model->fresh()->getAttributes();
    }

    expect($originalModels)->toHaveCount(15);
    expect($originalSnapshots)->toHaveCount(15);

    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    expect($this->sourceEdition->stampEntries()->count())
        ->toBe(2);

    expect($this->sourceEdition->stampValues()->count())
        ->toBe(4);

    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);

    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    expect($this->history->getActiveBatch())->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    // ---------------------------------------------------------
    // Livewire-duplicatieformulier voorbereiden.
    // ---------------------------------------------------------

    $component = Livewire::test($this->componentName, [
        'catalog' => $this->catalog,
    ])
        ->set('edition_name', 'Editie 2027')
        ->set('edition_year', 2027)
        ->set('publication_date', '2027-01-15')
        ->set('currency', 'EUR')
        ->set('notes', 'Opruimingstest na een mislukte duplicatie.')
        ->set('source_edition_id', $this->sourceEdition->id)
        ->set('copy_catalog_values', true);

    $component
        ->assertSet('edition_name', 'Editie 2027')
        ->assertSet('edition_year', 2027)
        ->assertSet('source_edition_id', $this->sourceEdition->id)
        ->assertSet('copy_catalog_values', true);

    $sessionKey = 'catalog_edition_duplication_pending.'
        .$component->instance()->getId();

    expect(session()->has($sessionKey))->toBeFalse();

    $component
        ->call('save')
        ->assertHasNoErrors();

    expect(session()->has($sessionKey))->toBeTrue();

    $confirmationHash = session()->get($sessionKey);

    expect($confirmationHash)->toBeString();
    expect($confirmationHash)->toMatch('/^[a-f0-9]{64}$/');

    // Controleer dat de voorbereiding geen gegevens heeft gewijzigd.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    expect($this->history->getActiveBatch())->toBeNull();
    expect(session()->has('success'))->toBeFalse();

    // ---------------------------------------------------------
    // Tijdelijke Eloquent-eventdispatcher voorbereiden.
    // ---------------------------------------------------------

    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    expect($originalDispatcher)->not->toBeNull();

    $temporaryDispatcher = clone $originalDispatcher;

    expect($temporaryDispatcher)->not->toBe($originalDispatcher);

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    /** @var CatalogChangeBatch|null $capturedBatch */
    $capturedBatch = null;

    $capturedServiceInstances = [];
    $capturedActiveBatchIds = [];
    $capturedLogIds = [];
    $capturedLogBatchIds = [];

    $createdLogCount = 0;
    $createdValueLogCount = 0;

    $stateBeforeFailure = [];

    expect($capturedHistory)->toBeNull();
    expect($capturedBatch)->toBeNull();
    expect($createdLogCount)->toBe(0);
    expect($createdValueLogCount)->toBe(0);
    expect($stateBeforeFailure)->toBeEmpty();

    // ---------------------------------------------------------
    // Actieve geschiedeniscontext vastleggen en fout forceren.
    // ---------------------------------------------------------

    $temporaryDispatcher->listen(
        'eloquent.created: '.CatalogChangeLog::class,
        function (CatalogChangeLog $log) use (
            &$capturedHistory,
            &$capturedBatch,
            &$capturedServiceInstances,
            &$capturedActiveBatchIds,
            &$capturedLogIds,
            &$capturedLogBatchIds,
            &$createdLogCount,
            &$createdValueLogCount,
            &$stateBeforeFailure
        ) {
            // Negeer eventuele wijzigingslogs van de broneditie.
            if ((int) $log->catalog_edition_id === (int) $this->sourceEdition->id) {
                return;
            }

            $history = app(CatalogChangeHistoryService::class);

            $activeBatch = $history->getActiveBatch();

            $createdLogCount++;

            $capturedHistory = $history;
            $capturedBatch = $activeBatch;

            $capturedServiceInstances[] = $history;
            $capturedActiveBatchIds[] = $activeBatch?->id;
            $capturedLogIds[] = $log->id;
            $capturedLogBatchIds[] = $log->catalog_change_batch_id;

            // Forceer de fout uitsluitend bij een aangemaakte waardelog.
            if (
                $log->record_type !== 'stamp_catalog_value'
                || $log->action !== 'created'
            ) {
                return;
            }

            $createdValueLogCount++;

            // Laat de eerste waardelog succesvol registreren.
            if ($createdValueLogCount !== 2) {
                return;
            }

            // Haal de daadwerkelijk opgeslagen doelgegevens op.
            $targetEdition = CatalogEdition::query()
                ->findOrFail($log->catalog_edition_id);

            $targetValue = StampCatalogValue::query()
                ->findOrFail($log->record_id);

            $targetEntry = StampCatalogEntry::query()
                ->findOrFail($targetValue->stamp_catalog_entry_id);

            $persistedLog = CatalogChangeLog::query()
                ->findOrFail($log->id);

            // Leg de tijdelijke databasestatus vóór de uitzondering vast.
            $stateBeforeFailure = [
                'catalog_count' => Catalog::count(),
                'edition_count' => CatalogEdition::count(),
                'stamp_count' => Stamp::count(),
                'entry_count' => StampCatalogEntry::count(),
                'value_count' => StampCatalogValue::count(),

                'target_edition_id' => $targetEdition->id,
                'target_edition_attributes' => $targetEdition->getAttributes(),

                'target_entry_count' => $targetEdition->stampEntries()->count(),

                'target_entries' => $targetEdition->stampEntries()
                    ->get()
                    ->map(
                        fn (StampCatalogEntry $entry) => $entry->getAttributes()
                    )
                    ->all(),

                'target_value_count' => $targetEdition->stampValues()->count(),

                'target_values' => StampCatalogValue::query()
                    ->where('stamp_catalog_entry_id', $targetEntry->id)
                    ->get()
                    ->map(
                        fn (StampCatalogValue $value) => $value->getAttributes()
                    )
                    ->all(),

                'source_entry_count' => $this->sourceEdition->stampEntries()->count(),
                'source_value_count' => $this->sourceEdition->stampValues()->count(),

                'batch_count' => CatalogChangeBatch::count(),
                'log_count' => CatalogChangeLog::count(),

                'active_batch_id' => $activeBatch?->id,
                'active_batch_attributes' => $activeBatch?->getAttributes(),

                'active_batch_logs' => $activeBatch?->logs()
                    ->get()
                    ->map(
                        fn (CatalogChangeLog $batchLog) => $batchLog->getAttributes()
                    )
                    ->all(),

                'triggering_log_id' => $log->id,
                'triggering_log_attributes' => $log->getAttributes(),
                'persisted_log_attributes' => $persistedLog->getAttributes(),

                'triggering_target_entry_attributes' => $targetEntry->getAttributes(),
                'triggering_target_value_attributes' => $targetValue->getAttributes(),
            ];

            throw new RuntimeException(
                'Geforceerde fout tijdens de tweede waardelog voor de opruimingstest vanuit Livewire.'
            );
        }
    );

    expect($createdLogCount)->toBe(0);
    expect($createdValueLogCount)->toBe(0);
    expect($capturedHistory)->toBeNull();
    expect($capturedBatch)->toBeNull();

    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // ---------------------------------------------------------
    // Mislukte duplicatie uitvoeren.
    // ---------------------------------------------------------

    expect(session()->has($sessionKey))->toBeTrue();

    expect(session()->get($sessionKey))
        ->toBe($confirmationHash);

    $this->travel(1)->minute();

    try {
        CatalogChangeLog::setEventDispatcher(
            $temporaryDispatcher
        );

        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($temporaryDispatcher);

        $this->withoutExceptionHandling();

        expect(
            fn () => $component->call('confirmSave')
        )->toThrow(
            RuntimeException::class,
            'Geforceerde fout tijdens de tweede waardelog voor de opruimingstest vanuit Livewire.'
        );
    } finally {
        CatalogChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }

    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // ---------------------------------------------------------
    // Vastgelegde geschiedenisservice en batch controleren.
    // ---------------------------------------------------------

    expect($createdLogCount)->toBe(3);
    expect($createdValueLogCount)->toBe(2);

    expect($stateBeforeFailure)->not->toBeEmpty();

    expect($capturedHistory)
        ->toBeInstanceOf(CatalogChangeHistoryService::class);

    expect($capturedBatch)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    expect($capturedHistory)
        ->toBe($this->history);

    expect($capturedHistory)
        ->toBe(app(CatalogChangeHistoryService::class));

    expect($capturedServiceInstances)->toHaveCount(3);
    expect($capturedActiveBatchIds)->toHaveCount(3);
    expect($capturedLogIds)->toHaveCount(3);
    expect($capturedLogBatchIds)->toHaveCount(3);

    expect(array_unique($capturedLogIds))
        ->toHaveCount(3);

    foreach ($capturedServiceInstances as $serviceInstance) {
        expect($serviceInstance)->toBe($capturedHistory);
    }

    foreach ($capturedActiveBatchIds as $activeBatchId) {
        expect($activeBatchId)->toBe($capturedBatch->id);
    }

    foreach ($capturedLogBatchIds as $logBatchId) {
        expect($logBatchId)->toBe($capturedBatch->id);
    }

    // ---------------------------------------------------------
    // Tijdelijke databasestatus vóór de fout controleren.
    // ---------------------------------------------------------

    expect($stateBeforeFailure['catalog_count'])->toBe(1);
    expect($stateBeforeFailure['edition_count'])->toBe(2);
    expect($stateBeforeFailure['stamp_count'])->toBe(2);
    expect($stateBeforeFailure['entry_count'])->toBe(3);
    expect($stateBeforeFailure['value_count'])->toBe(6);

    expect($stateBeforeFailure['source_entry_count'])->toBe(2);
    expect($stateBeforeFailure['source_value_count'])->toBe(4);

    expect($stateBeforeFailure['target_entry_count'])->toBe(1);
    expect($stateBeforeFailure['target_value_count'])->toBe(2);

    expect($stateBeforeFailure['target_entries'])->toHaveCount(1);
    expect($stateBeforeFailure['target_values'])->toHaveCount(2);

    expect($stateBeforeFailure['batch_count'])->toBe(1);
    expect($stateBeforeFailure['log_count'])->toBe(3);

    expect($stateBeforeFailure['active_batch_id'])
        ->toBe($capturedBatch->id);

    $temporaryTargetEditionId =
        $stateBeforeFailure['target_edition_id'];

    expect($temporaryTargetEditionId)->toBeInt();

    expect($temporaryTargetEditionId)
        ->not->toBe($this->sourceEdition->id);

    $temporaryTargetEdition =
        $stateBeforeFailure['target_edition_attributes'];

    expect($temporaryTargetEdition['id'])
        ->toBe($temporaryTargetEditionId);

    expect($temporaryTargetEdition['catalog_id'])
        ->toBe($this->catalog->id);

    expect($temporaryTargetEdition['edition_name'])
        ->toBe('Editie 2027');

    expect($temporaryTargetEdition['edition_year'])
        ->toBe(2027);

    expect(
        Carbon::parse(
            $temporaryTargetEdition['publication_date']
        )->toDateString()
    )->toBe('2027-01-15');

    expect($temporaryTargetEdition['currency'])
        ->toBe('EUR');

    expect($temporaryTargetEdition['notes'])
        ->toBe('Opruimingstest na een mislukte duplicatie.');

    // Controleer de tijdelijk opgeslagen doelvermelding.
    $temporaryTargetEntry =
        $stateBeforeFailure['target_entries'][0];

    expect($temporaryTargetEntry['id'])->toBeInt();

    expect($temporaryTargetEntry['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    $matchingSourceEntry = collect([
        $sourceEntryOne,
        $sourceEntryTwo,
    ])->first(
        fn (StampCatalogEntry $entry) => (int) $entry->stamp_id === (int) $temporaryTargetEntry['stamp_id']
    );

    expect($matchingSourceEntry)
        ->toBeInstanceOf(StampCatalogEntry::class);

    expect($temporaryTargetEntry['id'])
        ->not->toBe($matchingSourceEntry->id);

    expect($temporaryTargetEntry['catalog_number'])
        ->toBe($matchingSourceEntry->catalog_number);

    expect($temporaryTargetEntry['notes'])
        ->toBe($matchingSourceEntry->notes);

    expect($stateBeforeFailure['triggering_target_entry_attributes'])
        ->toEqual($temporaryTargetEntry);

    // Controleer beide tijdelijk opgeslagen doelwaarden.
    foreach ($stateBeforeFailure['target_values'] as $temporaryValue) {
        expect($temporaryValue['id'])->toBeInt();

        expect($temporaryValue['stamp_catalog_entry_id'])
            ->toBe($temporaryTargetEntry['id']);

        $matchingSourceValue = $matchingSourceEntry->values()
            ->where('condition_id', $temporaryValue['condition_id'])
            ->sole();

        expect($temporaryValue['id'])
            ->not->toBe($matchingSourceValue->id);

        expect((float) $temporaryValue['catalog_value'])
            ->toBe((float) $matchingSourceValue->catalog_value);

        expect($temporaryValue['currency'])
            ->toBe($matchingSourceValue->currency);

        expect($temporaryValue['notes'])
            ->toBe($matchingSourceValue->notes);

        expect(
            in_array($temporaryValue['needs_review'], [true, 1], true)
        )->toBeTrue();

        expect($temporaryValue['created_at'])->not->toBeNull();
        expect($temporaryValue['updated_at'])->not->toBeNull();
    }

    // ---------------------------------------------------------
    // Tijdelijke geschiedenisregistratie controleren.
    // ---------------------------------------------------------

    $temporaryBatch =
        $stateBeforeFailure['active_batch_attributes'];

    expect($temporaryBatch)->toBeArray();

    expect($temporaryBatch['id'])
        ->toBe($capturedBatch->id);

    expect($temporaryBatch['catalog_edition_id'])
        ->toBe($temporaryTargetEditionId);

    expect($temporaryBatch['user_id'])
        ->toBe($this->user->id);

    expect($temporaryBatch['user_name'])
        ->toBe($this->user->name);

    expect($temporaryBatch['operation'])
        ->toBe('duplicate');

    expect($temporaryBatch['description'])
        ->toBe(
            'Duplicatie van catalogusvermeldingen vanuit '
            .$this->sourceEdition->edition_name
        );

    expect($temporaryBatch['catalog_name'])
        ->toBe($this->catalog->name);

    expect($temporaryBatch['catalog_edition_name'])
        ->toBe('Editie 2027');

    expect($temporaryBatch['created_at'])->not->toBeNull();
    expect($temporaryBatch['updated_at'])->not->toBeNull();

    $temporaryLogs = collect(
        $stateBeforeFailure['active_batch_logs']
    );

    expect($temporaryLogs)->toHaveCount(3);

    expect(
        $temporaryLogs
            ->where('record_type', 'stamp_catalog_entry')
            ->count()
    )->toBe(1);

    expect(
        $temporaryLogs
            ->where('record_type', 'stamp_catalog_value')
            ->count()
    )->toBe(2);

    expect(
        $temporaryLogs
            ->pluck('id')
            ->sort()
            ->values()
            ->all()
    )->toEqual(
        collect($capturedLogIds)
            ->sort()
            ->values()
            ->all()
    );

    // Controleer de inhoud van alle tijdelijke wijzigingslogs.
    foreach ($temporaryLogs as $temporaryLog) {
        expect($temporaryLog['catalog_change_batch_id'])
            ->toBe($temporaryBatch['id']);

        expect($temporaryLog['catalog_edition_id'])
            ->toBe($temporaryTargetEditionId);

        expect($temporaryLog['stamp_id'])
            ->toBe($temporaryTargetEntry['stamp_id']);

        expect($temporaryLog['stamp_series_id'])
            ->toBeNull();

        expect($temporaryLog['action'])
            ->toBe('created');

        expect($temporaryLog['old_values'])
            ->toBeNull();

        expect($temporaryLog['new_values'])
            ->toBeString();

        expect($temporaryLog['created_at'])->not->toBeNull();
        expect($temporaryLog['updated_at'])->not->toBeNull();

        $newValues = json_decode(
            $temporaryLog['new_values'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        expect($newValues)->toBeArray();

        if ($temporaryLog['record_type'] === 'stamp_catalog_entry') {
            expect($temporaryLog['record_id'])
                ->toBe($temporaryTargetEntry['id']);

            expect($temporaryLog['condition_id'])
                ->toBeNull();

            expect($newValues)
                ->toEqual($temporaryTargetEntry);
        } else {
            expect($temporaryLog['record_type'])
                ->toBe('stamp_catalog_value');

            $matchingTargetValue = collect(
                $stateBeforeFailure['target_values']
            )->first(
                fn (array $value) => (int) $value['id'] === (int) $temporaryLog['record_id']
            );

            expect($matchingTargetValue)->toBeArray();

            expect($temporaryLog['condition_id'])
                ->toBe($matchingTargetValue['condition_id']);

            expect(
                array_key_exists('laravel_through_key', $newValues)
            )->toBeFalse();

            // Normaliseer uitsluitend bekende verschillen in gegevenstypen.
            $newValues['catalog_value'] =
                (float) $newValues['catalog_value'];

            $matchingTargetValue['catalog_value'] =
                (float) $matchingTargetValue['catalog_value'];

            $newValues['needs_review'] =
                (bool) $newValues['needs_review'];

            $matchingTargetValue['needs_review'] =
                (bool) $matchingTargetValue['needs_review'];

            expect($newValues)
                ->toEqual($matchingTargetValue);
        }
    }

    // Controleer de wijzigingslog die de uitzondering veroorzaakte.
    $triggeringLog =
        $stateBeforeFailure['triggering_log_attributes'];

    $persistedLog =
        $stateBeforeFailure['persisted_log_attributes'];

    expect($triggeringLog)->toBeArray();
    expect($persistedLog)->toBeArray();

    expect($triggeringLog['id'])
        ->toBe($stateBeforeFailure['triggering_log_id']);

    expect($triggeringLog['record_type'])
        ->toBe('stamp_catalog_value');

    expect($triggeringLog['record_id'])
        ->toBe(
            $stateBeforeFailure['triggering_target_value_attributes']['id']
        );

    expect($triggeringLog)
        ->toEqual($persistedLog);

    expect(
        $temporaryLogs->firstWhere('id', $triggeringLog['id'])
    )->toEqual($persistedLog);

    // ---------------------------------------------------------
    // Definitieve rollback controleren.
    // ---------------------------------------------------------

    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    expect($this->sourceEdition->stampEntries()->count())
        ->toBe(2);

    expect($this->sourceEdition->stampValues()->count())
        ->toBe(4);

    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);

    expect(
        CatalogEdition::query()
            ->whereKey($temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    expect(
        CatalogEdition::query()
            ->where('catalog_id', $this->catalog->id)
            ->where('edition_name', 'Editie 2027')
            ->where('edition_year', 2027)
            ->exists()
    )->toBeFalse();

    expect(
        StampCatalogEntry::query()
            ->whereKey($temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    expect(
        StampCatalogEntry::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    foreach ($stateBeforeFailure['target_values'] as $temporaryValue) {
        expect(
            StampCatalogValue::query()
                ->whereKey($temporaryValue['id'])
                ->exists()
        )->toBeFalse();
    }

    expect(
        StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $temporaryTargetEntry['id'])
            ->exists()
    )->toBeFalse();

    // Controleer dat alle oorspronkelijke modellen ongewijzigd zijn.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer dat de tijdelijke geschiedenisbatch is teruggedraaid.
    expect(
        CatalogChangeBatch::query()
            ->whereKey($temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    expect($capturedBatch->fresh())->toBeNull();

    // Controleer dat alle tijdelijke wijzigingslogs zijn teruggedraaid.
    foreach ($capturedLogIds as $logId) {
        expect(
            CatalogChangeLog::query()
                ->whereKey($logId)
                ->exists()
        )->toBeFalse();
    }

    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // ---------------------------------------------------------
    // Opruiming van de actieve geschiedenisbatch controleren.
    // ---------------------------------------------------------

    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    expect(session()->has($sessionKey))->toBeFalse();
    expect(session()->has('success'))->toBeFalse();

    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    // ---------------------------------------------------------
    // Rechtstreekse modelwijziging na de mislukte duplicatie.
    // ---------------------------------------------------------

    // Gebruik een bestaande bronwaarde, omdat de doelwaarden
    // na de rollback niet meer bestaan.
    $valueToUpdate = $sourceValueOne->fresh();

    expect($valueToUpdate)->not->toBeNull();

    expect($valueToUpdate->catalog_value)
        ->toBe('19.50');

    expect($valueToUpdate->needs_review)
        ->toBeFalse();

    $valueBeforeUpdate = $valueToUpdate->getAttributes();

    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    $this->travel(2)->minutes();

    try {
        $valueToUpdate->update([
            'catalog_value' => 49.50,
        ]);
    } finally {
        $this->travelBack();
    }

    $updatedValue = $valueToUpdate->fresh();

    expect($updatedValue)->not->toBeNull();

    expect($updatedValue->catalog_value)
        ->toBe('49.50');

    // De bestaande modelwerking moet de controlemarkering activeren.
    expect($updatedValue->needs_review)
        ->toBeTrue();

    expect($updatedValue->getAttributes()['updated_at'])
        ->not->toBe($valueBeforeUpdate['updated_at']);

    // Controleer dat uitsluitend de verwachte attributen zijn gewijzigd.
    $expectedUpdatedAttributes = $valueBeforeUpdate;

    $expectedUpdatedAttributes['catalog_value'] =
        $updatedValue->getAttributes()['catalog_value'];

    $expectedUpdatedAttributes['needs_review'] =
        $updatedValue->getAttributes()['needs_review'];

    $expectedUpdatedAttributes['updated_at'] =
        $updatedValue->getAttributes()['updated_at'];

    expect($updatedValue->getAttributes())
        ->toEqual($expectedUpdatedAttributes);

    // ---------------------------------------------------------
    // Definitieve gegevens- en geschiedeniscontroles.
    // ---------------------------------------------------------

    // Er mag geen nieuwe geschiedenisregistratie zijn ontstaan.
    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    expect(
        CatalogChangeLog::query()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedValue->id)
            ->exists()
    )->toBeFalse();

    expect(
        CatalogChangeBatch::query()
            ->whereKey($temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    expect(
        CatalogChangeLog::query()
            ->where('catalog_change_batch_id', $temporaryBatch['id'])
            ->exists()
    )->toBeFalse();

    expect(
        CatalogChangeBatch::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    expect(
        CatalogChangeLog::query()
            ->where('catalog_edition_id', $temporaryTargetEditionId)
            ->exists()
    )->toBeFalse();

    // Controleer alle oorspronkelijke modellen opnieuw.
    // Alleen de bewust gewijzigde bronwaarde mag afwijken.
    foreach ($originalModels as $index => $model) {
        $freshModel = $model->fresh();

        expect($freshModel)->not->toBeNull();

        if ($model->is($sourceValueOne)) {
            expect($freshModel->getAttributes())
                ->toEqual($expectedUpdatedAttributes);

            continue;
        }

        expect($freshModel->getAttributes())
            ->toEqual($originalSnapshots[$index]);
    }

    // Controleer de definitieve databaseaantallen.
    expect(Catalog::count())->toBe(1);
    expect(CatalogEdition::count())->toBe(1);
    expect(Stamp::count())->toBe(2);
    expect(StampCatalogEntry::count())->toBe(2);
    expect(StampCatalogValue::count())->toBe(4);

    expect($this->sourceEdition->stampEntries()->count())
        ->toBe(2);

    expect($this->sourceEdition->stampValues()->count())
        ->toBe(4);

    expect($sourceEntryOne->values()->count())->toBe(2);
    expect($sourceEntryTwo->values()->count())->toBe(2);

    expect(CatalogChangeBatch::count())->toBe(0);
    expect(CatalogChangeLog::count())->toBe(0);

    // Controleer dat geen actieve geschiedenisbatch is achtergebleven.
    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    expect(session()->has($sessionKey))->toBeFalse();
    expect(session()->has('success'))->toBeFalse();

    expect(auth()->id())->toBe($this->user->id);
    expect(auth()->user()->is_admin)->toBeFalse();

    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);
});
