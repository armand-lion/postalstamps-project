<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Services\CatalogEditionDuplicationService;

beforeEach(function () {
    app()->setLocale('nl');

    // Maak een land aan voor de postzegels.
    $this->country = Country::create([
        'name' => 'Testland Aanvullende Duplicatie',
        'iso_code' => 'AD',
        'entity_type' => 'country',
        'slug' => 'testland-aanvullende-duplicatie',
        'is_active' => true,
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Aanvullende Duplicatie',
        'abbreviation' => 'TAD',
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

    // Maak de duplicatieservice beschikbaar.
    $this->service = app(CatalogEditionDuplicationService::class);
});

test('meerdere postzegelvermeldingen worden tegelijkertijd gekopieerd', function () {
    // Maak drie verschillende postzegels aan.
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

    // Maak drie catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Notities eerste postzegel.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Notities tweede postzegel.',
    ]);

    $sourceEntryThree = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampThree->id,
        'catalog_number' => '300c',
        'notes' => 'Notities derde postzegel.',
    ]);

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(3)
        ->and(StampCatalogValue::count())->toBe(0);

    // Voer de duplicatiebewerking uit.
    $result = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition
    );

    // Controleer de teruggegeven aantallen.
    expect($result)->toBe([
        'copied' => 3,
        'skipped' => 0,
    ]);

    // Controleer de aantallen na de duplicatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(3)
        ->and(StampCatalogEntry::count())->toBe(6)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and(Stamp::count())->toBe(3)
        ->and(CatalogEdition::count())->toBe(2);

    // Haal de drie gekopieerde catalogusvermeldingen op.
    $targetEntryOne = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stampOne->id)
        ->firstOrFail();

    $targetEntryTwo = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stampTwo->id)
        ->firstOrFail();

    $targetEntryThree = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stampThree->id)
        ->firstOrFail();

    // Controleer de eerste gekopieerde catalogusvermelding.
    expect($targetEntryOne->id)->not->toBe($sourceEntryOne->id)
        ->and($targetEntryOne->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntryOne->stamp_id)->toBe($stampOne->id)
        ->and($targetEntryOne->catalog_number)->toBe('100a')
        ->and($targetEntryOne->notes)->toBe('Notities eerste postzegel.')
        ->and($targetEntryOne->values()->count())->toBe(0);

    // Controleer de tweede gekopieerde catalogusvermelding.
    expect($targetEntryTwo->id)->not->toBe($sourceEntryTwo->id)
        ->and($targetEntryTwo->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntryTwo->stamp_id)->toBe($stampTwo->id)
        ->and($targetEntryTwo->catalog_number)->toBe('200b')
        ->and($targetEntryTwo->notes)->toBe('Notities tweede postzegel.')
        ->and($targetEntryTwo->values()->count())->toBe(0);

    // Controleer de derde gekopieerde catalogusvermelding.
    expect($targetEntryThree->id)->not->toBe($sourceEntryThree->id)
        ->and($targetEntryThree->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntryThree->stamp_id)->toBe($stampThree->id)
        ->and($targetEntryThree->catalog_number)->toBe('300c')
        ->and($targetEntryThree->notes)->toBe('Notities derde postzegel.')
        ->and($targetEntryThree->values()->count())->toBe(0);

    // Controleer of de oorspronkelijke catalogusvermeldingen nog bestaan.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $sourceEntryOne->id,
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Notities eerste postzegel.',
    ]);

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $sourceEntryTwo->id,
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Notities tweede postzegel.',
    ]);

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $sourceEntryThree->id,
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampThree->id,
        'catalog_number' => '300c',
        'notes' => 'Notities derde postzegel.',
    ]);
});

test('nieuwe postzegelvermeldingen worden gekopieerd en bestaande vermeldingen worden overgeslagen', function () {
    // Maak drie verschillende postzegels aan.
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

    // Maak drie catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Bronvermelding eerste postzegel.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding tweede postzegel.',
    ]);

    $sourceEntryThree = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampThree->id,
        'catalog_number' => '300c',
        'notes' => 'Bronvermelding derde postzegel.',
    ]);

    // Maak voor de tweede postzegel alvast een vermelding
    // aan in de doeleditie met afwijkende gegevens.
    $existingTargetEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->targetEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '250x',
        'notes' => 'Bestaande notities in de doeleditie.',
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalSourceEntryOne = $sourceEntryOne->fresh()->getAttributes();
    $originalSourceEntryTwo = $sourceEntryTwo->fresh()->getAttributes();
    $originalSourceEntryThree = $sourceEntryThree->fresh()->getAttributes();

    $originalTargetEntry = $existingTargetEntry->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(4)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and(Stamp::count())->toBe(3);

    // Verplaats de testtijd zodat eventuele ongewenste wijzigingen
    // aan updated_at eveneens zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatiebewerking uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition
        );

        // Controleer de teruggegeven aantallen.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 1,
        ]);

        // Controleer de aantallen na duplicatie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(3)
            ->and(StampCatalogEntry::count())->toBe(6)
            ->and(StampCatalogValue::count())->toBe(0)
            ->and(Stamp::count())->toBe(3)
            ->and(CatalogEdition::count())->toBe(2);

        // Haal de drie vermeldingen uit de doeleditie op.
        $targetEntryOne = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)
            ->firstOrFail();

        $targetEntryTwo = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampTwo->id)
            ->firstOrFail();

        $targetEntryThree = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampThree->id)
            ->firstOrFail();

        // Controleer de eerste nieuw gekopieerde vermelding.
        expect($targetEntryOne->id)->not->toBe($sourceEntryOne->id)
            ->and($targetEntryOne->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryOne->stamp_id)->toBe($stampOne->id)
            ->and($targetEntryOne->catalog_number)->toBe('100a')
            ->and($targetEntryOne->notes)->toBe('Bronvermelding eerste postzegel.')
            ->and($targetEntryOne->values()->count())->toBe(0);

        // Controleer of de bestaande vermelding volledig ongewijzigd is.
        expect($targetEntryTwo->id)->toBe($existingTargetEntry->id)
            ->and($targetEntryTwo->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryTwo->stamp_id)->toBe($stampTwo->id)
            ->and($targetEntryTwo->catalog_number)->toBe('250x')
            ->and($targetEntryTwo->notes)->toBe('Bestaande notities in de doeleditie.')
            ->and($targetEntryTwo->getAttributes())->toBe($originalTargetEntry)
            ->and($targetEntryTwo->values()->count())->toBe(0);

        // Controleer de derde nieuw gekopieerde vermelding.
        expect($targetEntryThree->id)->not->toBe($sourceEntryThree->id)
            ->and($targetEntryThree->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryThree->stamp_id)->toBe($stampThree->id)
            ->and($targetEntryThree->catalog_number)->toBe('300c')
            ->and($targetEntryThree->notes)->toBe('Bronvermelding derde postzegel.')
            ->and($targetEntryThree->values()->count())->toBe(0);

        // Controleer dat alle oorspronkelijke bronvermeldingen
        // volledig ongewijzigd zijn gebleven.
        expect($sourceEntryOne->fresh()->getAttributes())->toBe($originalSourceEntryOne)
            ->and($sourceEntryTwo->fresh()->getAttributes())->toBe($originalSourceEntryTwo)
            ->and($sourceEntryThree->fresh()->getAttributes())->toBe($originalSourceEntryThree);

        // Controleer dat iedere postzegel precies één vermelding
        // heeft binnen de doeleditie.
        expect($this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()
                ->where('stamp_id', $stampTwo->id)->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()
                ->where('stamp_id', $stampThree->id)->count())->toBe(1);

        // Controleer dat de bestaande vermelding niet is overschreven.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $existingTargetEntry->id,
            'catalog_edition_id' => $this->targetEdition->id,
            'stamp_id' => $stampTwo->id,
            'catalog_number' => '250x',
            'notes' => 'Bestaande notities in de doeleditie.',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->targetEdition->id,
            'stamp_id' => $stampTwo->id,
            'catalog_number' => '200b',
        ]);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('meerdere postzegelvermeldingen inclusief cataloguswaarden worden tegelijkertijd gekopieerd', function () {
    // Maak twee verschillende postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Bronvermelding eerste postzegel.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding tweede postzegel.',
    ]);

    // Maak de cataloguswaarden voor de eerste postzegel aan.
    $sourceValueOne = $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Eerste postzegel postfris.',
        'needs_review' => false,
    ]);

    $sourceValueTwo = $sourceEntryOne->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '5.25',
        'currency' => 'EUR',
        'notes' => 'Eerste postzegel gestempeld.',
        'needs_review' => false,
    ]);

    // Maak de cataloguswaarden voor de tweede postzegel aan.
    $sourceValueThree = $sourceEntryTwo->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '22.50',
        'currency' => 'EUR',
        'notes' => 'Tweede postzegel postfris.',
        'needs_review' => false,
    ]);

    $sourceValueFour = $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Tweede postzegel gestempeld.',
        'needs_review' => false,
    ]);

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalSourceValueOne = $sourceValueOne->fresh()->getAttributes();
    $originalSourceValueTwo = $sourceValueTwo->fresh()->getAttributes();
    $originalSourceValueThree = $sourceValueThree->fresh()->getAttributes();
    $originalSourceValueFour = $sourceValueFour->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(4)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(4);

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Dupliceer de vermeldingen inclusief cataloguswaarden.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true
        );

        // Controleer de teruggegeven aantallen.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 0,
        ]);

        // Controleer de aantallen na duplicatie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(4)
            ->and($this->targetEdition->stampValues()->count())->toBe(4)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(8)
            ->and(Stamp::count())->toBe(2)
            ->and(CatalogEdition::count())->toBe(2);

        // Haal de gekopieerde catalogusvermeldingen op.
        $targetEntryOne = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)
            ->firstOrFail();

        $targetEntryTwo = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampTwo->id)
            ->firstOrFail();

        // Controleer de gekopieerde catalogusvermeldingen.
        expect($targetEntryOne->id)->not->toBe($sourceEntryOne->id)
            ->and($targetEntryOne->catalog_number)->toBe('100a')
            ->and($targetEntryOne->notes)->toBe('Bronvermelding eerste postzegel.')
            ->and($targetEntryOne->values()->count())->toBe(2)
            ->and($targetEntryTwo->id)->not->toBe($sourceEntryTwo->id)
            ->and($targetEntryTwo->catalog_number)->toBe('200b')
            ->and($targetEntryTwo->notes)->toBe('Bronvermelding tweede postzegel.')
            ->and($targetEntryTwo->values()->count())->toBe(2);

        // Haal de vier gekopieerde cataloguswaarden op.
        $targetValueOne = $targetEntryOne->values()
            ->where('condition_id', $this->postfris->id)
            ->firstOrFail();

        $targetValueTwo = $targetEntryOne->values()
            ->where('condition_id', $this->gestempeld->id)
            ->firstOrFail();

        $targetValueThree = $targetEntryTwo->values()
            ->where('condition_id', $this->postfris->id)
            ->firstOrFail();

        $targetValueFour = $targetEntryTwo->values()
            ->where('condition_id', $this->gestempeld->id)
            ->firstOrFail();

        // Controleer de eerste gekopieerde cataloguswaarde.
        expect($targetValueOne->id)->not->toBe($sourceValueOne->id)
            ->and($targetValueOne->stamp_catalog_entry_id)->toBe($targetEntryOne->id)
            ->and($targetValueOne->condition_id)->toBe($this->postfris->id)
            ->and($targetValueOne->catalog_value)->toBe('15.75')
            ->and($targetValueOne->currency)->toBe('EUR')
            ->and($targetValueOne->notes)->toBe('Eerste postzegel postfris.')
            ->and($targetValueOne->needs_review)->toBeTrue();

        // Controleer de tweede gekopieerde cataloguswaarde.
        expect($targetValueTwo->id)->not->toBe($sourceValueTwo->id)
            ->and($targetValueTwo->stamp_catalog_entry_id)->toBe($targetEntryOne->id)
            ->and($targetValueTwo->condition_id)->toBe($this->gestempeld->id)
            ->and($targetValueTwo->catalog_value)->toBe('5.25')
            ->and($targetValueTwo->currency)->toBe('EUR')
            ->and($targetValueTwo->notes)->toBe('Eerste postzegel gestempeld.')
            ->and($targetValueTwo->needs_review)->toBeTrue();

        // Controleer de derde gekopieerde cataloguswaarde.
        expect($targetValueThree->id)->not->toBe($sourceValueThree->id)
            ->and($targetValueThree->stamp_catalog_entry_id)->toBe($targetEntryTwo->id)
            ->and($targetValueThree->condition_id)->toBe($this->postfris->id)
            ->and($targetValueThree->catalog_value)->toBe('22.50')
            ->and($targetValueThree->currency)->toBe('EUR')
            ->and($targetValueThree->notes)->toBe('Tweede postzegel postfris.')
            ->and($targetValueThree->needs_review)->toBeTrue();

        // Controleer de vierde gekopieerde cataloguswaarde,
        // inclusief de expliciete nulwaarde.
        expect($targetValueFour->id)->not->toBe($sourceValueFour->id)
            ->and($targetValueFour->stamp_catalog_entry_id)->toBe($targetEntryTwo->id)
            ->and($targetValueFour->condition_id)->toBe($this->gestempeld->id)
            ->and($targetValueFour->catalog_value)->toBe('0.00')
            ->and($targetValueFour->currency)->toBe('EUR')
            ->and($targetValueFour->notes)->toBe('Tweede postzegel gestempeld.')
            ->and($targetValueFour->needs_review)->toBeTrue();

        // Controleer dat alle oorspronkelijke cataloguswaarden
        // volledig ongewijzigd zijn gebleven.
        expect($sourceValueOne->fresh()->getAttributes())->toBe($originalSourceValueOne)
            ->and($sourceValueTwo->fresh()->getAttributes())->toBe($originalSourceValueTwo)
            ->and($sourceValueThree->fresh()->getAttributes())->toBe($originalSourceValueThree)
            ->and($sourceValueFour->fresh()->getAttributes())->toBe($originalSourceValueFour);

        // Controleer dat de oorspronkelijke cataloguswaarden
        // niet opnieuw ter controle zijn gemarkeerd.
        expect($sourceValueOne->fresh()->needs_review)->toBeFalse()
            ->and($sourceValueTwo->fresh()->needs_review)->toBeFalse()
            ->and($sourceValueThree->fresh()->needs_review)->toBeFalse()
            ->and($sourceValueFour->fresh()->needs_review)->toBeFalse();
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een lege broneditie kan zonder wijzigingen worden gedupliceerd', function () {
    // Bewaar de oorspronkelijke gegevens van beide catalogusedities.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke catalogusgegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();

    // Controleer de lege uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(0)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and(Stamp::count())->toBe(0)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Catalog::count())->toBe(1);

    // Controleer de aantallen die de duplicatieservice berekent.
    expect($this->service->countSourceEntries($this->sourceEdition))->toBe(0)
        ->and($this->service->countExistingEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(0)
        ->and($this->service->countCopyableEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(0);

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Dupliceer de lege broneditie zonder cataloguswaarden.
        $resultWithoutValues = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            false
        );

        // Controleer de teruggegeven aantallen.
        expect($resultWithoutValues)->toBe([
            'copied' => 0,
            'skipped' => 0,
        ]);

        // Controleer dat er geen gegevens zijn aangemaakt.
        expect($this->sourceEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and(StampCatalogEntry::count())->toBe(0)
            ->and(StampCatalogValue::count())->toBe(0)
            ->and(Stamp::count())->toBe(0)
            ->and(CatalogEdition::count())->toBe(2);

        // Dupliceer de lege broneditie inclusief cataloguswaarden.
        $resultWithValues = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true
        );

        // Controleer opnieuw de teruggegeven aantallen.
        expect($resultWithValues)->toBe([
            'copied' => 0,
            'skipped' => 0,
        ]);

        // Controleer de aantallen na beide duplicatiebewerkingen.
        expect($this->sourceEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->sourceEdition->stampValues()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0)
            ->and(StampCatalogEntry::count())->toBe(0)
            ->and(StampCatalogValue::count())->toBe(0)
            ->and(Stamp::count())->toBe(0)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Catalog::count())->toBe(1)
            ->and(Country::count())->toBe(1)
            ->and(Condition::count())->toBe(2);

        // Controleer dat beide catalogusedities volledig
        // ongewijzigd zijn gebleven.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())
            ->toBe($originalTargetEdition);

        // Controleer dat ook de catalogus ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalog);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('herhaalde duplicatie maakt geen dubbele catalogusvermeldingen of cataloguswaarden aan', function () {
    // Maak twee verschillende postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Bronvermelding eerste postzegel.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding tweede postzegel.',
    ]);

    // Maak voor beide postzegels een cataloguswaarde aan.
    $sourceValueOne = $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde.',
        'needs_review' => false,
    ]);

    $sourceValueTwo = $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde nulwaarde.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke brongegevens.
    $originalSourceEntryOne = $sourceEntryOne->fresh()->getAttributes();
    $originalSourceEntryTwo = $sourceEntryTwo->fresh()->getAttributes();
    $originalSourceValueOne = $sourceValueOne->fresh()->getAttributes();
    $originalSourceValueTwo = $sourceValueTwo->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(2);

    // Controleer de beschikbare aantallen vóór duplicatie.
    expect($this->service->countSourceEntries($this->sourceEdition))->toBe(2)
        ->and($this->service->countExistingEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(0)
        ->and($this->service->countCopyableEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(2);

    // Voer de eerste duplicatiebewerking uit.
    $firstResult = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition,
        true
    );

    expect($firstResult)->toBe([
        'copied' => 2,
        'skipped' => 0,
    ]);

    // Controleer de aantallen na de eerste duplicatie.
    expect($this->targetEdition->stampEntries()->count())->toBe(2)
        ->and($this->targetEdition->stampValues()->count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(4)
        ->and(StampCatalogValue::count())->toBe(4);

    // Haal de gekopieerde catalogusvermeldingen op.
    $targetEntryOne = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stampOne->id)
        ->firstOrFail();

    $targetEntryTwo = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stampTwo->id)
        ->firstOrFail();

    // Haal de gekopieerde cataloguswaarden op.
    $targetValueOne = $targetEntryOne->values()
        ->where('condition_id', $this->postfris->id)
        ->firstOrFail();

    $targetValueTwo = $targetEntryTwo->values()
        ->where('condition_id', $this->gestempeld->id)
        ->firstOrFail();

    // Controleer de gekopieerde gegevens.
    expect($targetEntryOne->catalog_number)->toBe('100a')
        ->and($targetEntryTwo->catalog_number)->toBe('200b')
        ->and($targetValueOne->catalog_value)->toBe('15.75')
        ->and($targetValueTwo->catalog_value)->toBe('0.00')
        ->and($targetValueOne->needs_review)->toBeTrue()
        ->and($targetValueTwo->needs_review)->toBeTrue();

    // Bewaar alle gegevens na de eerste duplicatie.
    $originalTargetEntryOne = $targetEntryOne->fresh()->getAttributes();
    $originalTargetEntryTwo = $targetEntryTwo->fresh()->getAttributes();
    $originalTargetValueOne = $targetValueOne->fresh()->getAttributes();
    $originalTargetValueTwo = $targetValueTwo->fresh()->getAttributes();

    // Controleer de beschikbare aantallen na de eerste duplicatie.
    expect($this->service->countSourceEntries($this->sourceEdition))->toBe(2)
        ->and($this->service->countExistingEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(2)
        ->and($this->service->countCopyableEntries(
            $this->sourceEdition,
            $this->targetEdition
        ))->toBe(0);

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Voer dezelfde duplicatiebewerking opnieuw uit.
        $secondResult = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true
        );

        expect($secondResult)->toBe([
            'copied' => 0,
            'skipped' => 2,
        ]);

        // Controleer de aantallen na de tweede duplicatie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(4);

        // Verplaats de testtijd opnieuw.
        $this->travel(1)->minutes();

        // Voer een derde duplicatiebewerking uit,
        // ditmaal zonder cataloguswaarden.
        $thirdResult = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            false
        );

        expect($thirdResult)->toBe([
            'copied' => 0,
            'skipped' => 2,
        ]);

        // Controleer de definitieve aantallen.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(4)
            ->and(Stamp::count())->toBe(2)
            ->and(CatalogEdition::count())->toBe(2);

        // Controleer dat de gekopieerde vermeldingen
        // volledig ongewijzigd zijn gebleven.
        expect($targetEntryOne->fresh()->getAttributes())
            ->toBe($originalTargetEntryOne)
            ->and($targetEntryTwo->fresh()->getAttributes())
            ->toBe($originalTargetEntryTwo);

        // Controleer dat de gekopieerde cataloguswaarden
        // volledig ongewijzigd zijn gebleven.
        expect($targetValueOne->fresh()->getAttributes())
            ->toBe($originalTargetValueOne)
            ->and($targetValueTwo->fresh()->getAttributes())
            ->toBe($originalTargetValueTwo);

        // Controleer dat alle oorspronkelijke brongegevens
        // volledig ongewijzigd zijn gebleven.
        expect($sourceEntryOne->fresh()->getAttributes())
            ->toBe($originalSourceEntryOne)
            ->and($sourceEntryTwo->fresh()->getAttributes())
            ->toBe($originalSourceEntryTwo)
            ->and($sourceValueOne->fresh()->getAttributes())
            ->toBe($originalSourceValueOne)
            ->and($sourceValueTwo->fresh()->getAttributes())
            ->toBe($originalSourceValueTwo);

        // Controleer dat iedere postzegel precies één vermelding
        // heeft binnen de doeleditie.
        expect($this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()
                ->where('stamp_id', $stampTwo->id)->count())->toBe(1)
            ->and($targetEntryOne->values()->count())->toBe(1)
            ->and($targetEntryTwo->values()->count())->toBe(1);

        // Controleer de definitieve beschikbare aantallen.
        expect($this->service->countSourceEntries($this->sourceEdition))->toBe(2)
            ->and($this->service->countExistingEntries(
                $this->sourceEdition,
                $this->targetEdition
            ))->toBe(2)
            ->and($this->service->countCopyableEntries(
                $this->sourceEdition,
                $this->targetEdition
            ))->toBe(0);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});
test('bronvermeldingen blijven volledig ongewijzigd tijdens duplicatie', function () {
    // Maak drie verschillende postzegels aan.
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

    // Maak bronvermeldingen aan met verschillende soorten gegevens.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '001a',
        'notes' => 'Oorspronkelijke notities met é, ë en ü.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '125-II',
        'notes' => null,
    ]);

    $sourceEntryThree = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampThree->id,
        'catalog_number' => 'A-300b',
        'notes' => "Nederlandse beschrijving.\nEnglish description.\nDeutsche Beschreibung.",
    ]);

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalSourceEntryOne = $sourceEntryOne->fresh()->getAttributes();
    $originalSourceEntryTwo = $sourceEntryTwo->fresh()->getAttributes();
    $originalSourceEntryThree = $sourceEntryThree->fresh()->getAttributes();

    // Bewaar de volledige verzameling bronvermeldingen.
    $originalSourceEntries = $this->sourceEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke gegevens van de broneditie.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(3)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and(Stamp::count())->toBe(3);

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatiebewerking uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition
        );

        // Controleer de teruggegeven aantallen.
        expect($result)->toBe([
            'copied' => 3,
            'skipped' => 0,
        ]);

        // Controleer de aantallen na duplicatie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(3)
            ->and(StampCatalogEntry::count())->toBe(6)
            ->and(StampCatalogValue::count())->toBe(0)
            ->and(Stamp::count())->toBe(3)
            ->and(CatalogEdition::count())->toBe(2);

        // Controleer iedere oorspronkelijke bronvermelding afzonderlijk.
        expect($sourceEntryOne->fresh()->getAttributes())
            ->toBe($originalSourceEntryOne)
            ->and($sourceEntryTwo->fresh()->getAttributes())
            ->toBe($originalSourceEntryTwo)
            ->and($sourceEntryThree->fresh()->getAttributes())
            ->toBe($originalSourceEntryThree);

        // Haal de volledige verzameling bronvermeldingen opnieuw op.
        $currentSourceEntries = $this->sourceEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all();

        // Controleer dat de volledige verzameling ongewijzigd is.
        expect($currentSourceEntries)->toBe($originalSourceEntries);

        // Controleer dat ook de broneditie zelf ongewijzigd is.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toBe($originalSourceEdition);

        // Haal de drie gekopieerde vermeldingen op.
        $targetEntryOne = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)
            ->firstOrFail();

        $targetEntryTwo = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampTwo->id)
            ->firstOrFail();

        $targetEntryThree = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampThree->id)
            ->firstOrFail();

        // Controleer het behoud van voorloopnullen
        // en bijzondere tekens in de notities.
        expect($targetEntryOne->id)->not->toBe($sourceEntryOne->id)
            ->and($targetEntryOne->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryOne->stamp_id)->toBe($stampOne->id)
            ->and($targetEntryOne->catalog_number)->toBe('001a')
            ->and($targetEntryOne->notes)->toBe('Oorspronkelijke notities met é, ë en ü.');

        // Controleer het behoud van een null-waarde bij notities.
        expect($targetEntryTwo->id)->not->toBe($sourceEntryTwo->id)
            ->and($targetEntryTwo->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryTwo->stamp_id)->toBe($stampTwo->id)
            ->and($targetEntryTwo->catalog_number)->toBe('125-II')
            ->and($targetEntryTwo->notes)->toBeNull();

        // Controleer het behoud van meertalige tekst en regeleinden.
        expect($targetEntryThree->id)->not->toBe($sourceEntryThree->id)
            ->and($targetEntryThree->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntryThree->stamp_id)->toBe($stampThree->id)
            ->and($targetEntryThree->catalog_number)->toBe('A-300b')
            ->and($targetEntryThree->notes)
            ->toBe("Nederlandse beschrijving.\nEnglish description.\nDeutsche Beschreibung.");

        // Controleer dat er geen cataloguswaarden zijn aangemaakt.
        expect($targetEntryOne->values()->count())->toBe(0)
            ->and($targetEntryTwo->values()->count())->toBe(0)
            ->and($targetEntryThree->values()->count())->toBe(0);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('cataloguswaarden van de broneditie blijven volledig ongewijzigd tijdens duplicatie', function () {
    // Maak twee verschillende postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Oorspronkelijke eerste bronvermelding.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Oorspronkelijke tweede bronvermelding.',
    ]);

    // Maak cataloguswaarden aan met verschillende eigenschappen.
    $sourceValueOne = $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '125.75',
        'currency' => 'EUR',
        'notes' => 'Bijzondere kwaliteit met é, ë en ü.',
        'needs_review' => false,
    ]);

    $sourceValueTwo = $sourceEntryOne->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => true,
    ]);

    $sourceValueThree = $sourceEntryTwo->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '250.50',
        'currency' => 'USD',
        'notes' => "Nederlandse notities.\nEnglish notes.",
        'needs_review' => true,
    ]);

    $sourceValueFour = $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.01',
        'currency' => 'GBP',
        'notes' => 'Minimale positieve cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalSourceValueOne = $sourceValueOne->fresh()->getAttributes();
    $originalSourceValueTwo = $sourceValueTwo->fresh()->getAttributes();
    $originalSourceValueThree = $sourceValueThree->fresh()->getAttributes();
    $originalSourceValueFour = $sourceValueFour->fresh()->getAttributes();

    // Bewaar ook de oorspronkelijke bronvermeldingen.
    $originalSourceEntryOne = $sourceEntryOne->fresh()->getAttributes();
    $originalSourceEntryTwo = $sourceEntryTwo->fresh()->getAttributes();

    // Bewaar de volledige verzameling oorspronkelijke cataloguswaarden.
    $originalSourceValues = StampCatalogValue::query()
        ->whereIn('stamp_catalog_entry_id', [
            $sourceEntryOne->id,
            $sourceEntryTwo->id,
        ])
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(4)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(4);

    // Controleer de oorspronkelijke controlemarkeringen.
    expect($sourceValueOne->fresh()->needs_review)->toBeFalse()
        ->and($sourceValueTwo->fresh()->needs_review)->toBeTrue()
        ->and($sourceValueThree->fresh()->needs_review)->toBeTrue()
        ->and($sourceValueFour->fresh()->needs_review)->toBeFalse();

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie inclusief cataloguswaarden uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true
        );

        // Controleer de teruggegeven aantallen.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 0,
        ]);

        // Controleer de aantallen na duplicatie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(4)
            ->and($this->targetEdition->stampValues()->count())->toBe(4)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(8)
            ->and(Stamp::count())->toBe(2)
            ->and(CatalogEdition::count())->toBe(2);

        // Controleer iedere oorspronkelijke cataloguswaarde afzonderlijk.
        expect($sourceValueOne->fresh()->getAttributes())
            ->toBe($originalSourceValueOne)
            ->and($sourceValueTwo->fresh()->getAttributes())
            ->toBe($originalSourceValueTwo)
            ->and($sourceValueThree->fresh()->getAttributes())
            ->toBe($originalSourceValueThree)
            ->and($sourceValueFour->fresh()->getAttributes())
            ->toBe($originalSourceValueFour);

        // Controleer dat de bronvermeldingen ongewijzigd zijn.
        expect($sourceEntryOne->fresh()->getAttributes())
            ->toBe($originalSourceEntryOne)
            ->and($sourceEntryTwo->fresh()->getAttributes())
            ->toBe($originalSourceEntryTwo);

        // Haal de volledige verzameling bronwaarden opnieuw op.
        $currentSourceValues = StampCatalogValue::query()
            ->whereIn('stamp_catalog_entry_id', [
                $sourceEntryOne->id,
                $sourceEntryTwo->id,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all();

        // Controleer dat de volledige verzameling ongewijzigd is.
        expect($currentSourceValues)->toBe($originalSourceValues);

        // Controleer opnieuw de oorspronkelijke controlemarkeringen.
        expect($sourceValueOne->fresh()->needs_review)->toBeFalse()
            ->and($sourceValueTwo->fresh()->needs_review)->toBeTrue()
            ->and($sourceValueThree->fresh()->needs_review)->toBeTrue()
            ->and($sourceValueFour->fresh()->needs_review)->toBeFalse();

        // Haal de gekopieerde catalogusvermeldingen op.
        $targetEntryOne = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)
            ->firstOrFail();

        $targetEntryTwo = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampTwo->id)
            ->firstOrFail();

        // Haal de vier gekopieerde cataloguswaarden op.
        $targetValueOne = $targetEntryOne->values()
            ->where('condition_id', $this->postfris->id)
            ->firstOrFail();

        $targetValueTwo = $targetEntryOne->values()
            ->where('condition_id', $this->gestempeld->id)
            ->firstOrFail();

        $targetValueThree = $targetEntryTwo->values()
            ->where('condition_id', $this->postfris->id)
            ->firstOrFail();

        $targetValueFour = $targetEntryTwo->values()
            ->where('condition_id', $this->gestempeld->id)
            ->firstOrFail();

        // Controleer de eerste gekopieerde cataloguswaarde.
        expect($targetValueOne->id)->not->toBe($sourceValueOne->id)
            ->and($targetValueOne->stamp_catalog_entry_id)->toBe($targetEntryOne->id)
            ->and($targetValueOne->condition_id)->toBe($this->postfris->id)
            ->and($targetValueOne->catalog_value)->toBe('125.75')
            ->and($targetValueOne->currency)->toBe('EUR')
            ->and($targetValueOne->notes)->toBe('Bijzondere kwaliteit met é, ë en ü.')
            ->and($targetValueOne->needs_review)->toBeTrue();

        // Controleer de gekopieerde nulwaarde met ontbrekende notities.
        expect($targetValueTwo->id)->not->toBe($sourceValueTwo->id)
            ->and($targetValueTwo->stamp_catalog_entry_id)->toBe($targetEntryOne->id)
            ->and($targetValueTwo->condition_id)->toBe($this->gestempeld->id)
            ->and($targetValueTwo->catalog_value)->toBe('0.00')
            ->and($targetValueTwo->currency)->toBe('EUR')
            ->and($targetValueTwo->notes)->toBeNull()
            ->and($targetValueTwo->needs_review)->toBeTrue();

        // Controleer het behoud van de Amerikaanse valuta
        // en de meerregelige notities.
        expect($targetValueThree->id)->not->toBe($sourceValueThree->id)
            ->and($targetValueThree->stamp_catalog_entry_id)->toBe($targetEntryTwo->id)
            ->and($targetValueThree->condition_id)->toBe($this->postfris->id)
            ->and($targetValueThree->catalog_value)->toBe('250.50')
            ->and($targetValueThree->currency)->toBe('USD')
            ->and($targetValueThree->notes)->toBe("Nederlandse notities.\nEnglish notes.")
            ->and($targetValueThree->needs_review)->toBeTrue();

        // Controleer het behoud van de minimale positieve waarde
        // en de Britse valuta.
        expect($targetValueFour->id)->not->toBe($sourceValueFour->id)
            ->and($targetValueFour->stamp_catalog_entry_id)->toBe($targetEntryTwo->id)
            ->and($targetValueFour->condition_id)->toBe($this->gestempeld->id)
            ->and($targetValueFour->catalog_value)->toBe('0.01')
            ->and($targetValueFour->currency)->toBe('GBP')
            ->and($targetValueFour->notes)->toBe('Minimale positieve cataloguswaarde.')
            ->and($targetValueFour->needs_review)->toBeTrue();

        // Controleer het aantal waarden per doelvermelding.
        expect($targetEntryOne->values()->count())->toBe(2)
            ->and($targetEntryTwo->values()->count())->toBe(2);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('andere catalogusedities blijven volledig ongewijzigd tijdens duplicatie', function () {
    // Maak twee verschillende postzegels aan.
    $stampOne = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $stampTwo = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een andere editie binnen dezelfde catalogus aan.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak een geheel andere catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere testcatalogus duplicatie',
        'abbreviation' => 'ATD',
    ]);

    $otherCatalog->countries()->attach($this->country->id);

    // Maak een editie binnen de andere catalogus aan.
    $otherCatalogEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'currency' => 'USD',
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $sourceEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '100a',
        'notes' => 'Eerste bronvermelding.',
    ]);

    $sourceEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '200b',
        'notes' => 'Tweede bronvermelding.',
    ]);

    // Maak cataloguswaarden aan in de broneditie.
    $sourceEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde.',
        'needs_review' => false,
    ]);

    $sourceEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '5.25',
        'currency' => 'EUR',
        'notes' => 'Gestempelde bronwaarde.',
        'needs_review' => false,
    ]);

    // Maak vermeldingen aan in de andere editie
    // binnen dezelfde catalogus.
    $otherEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $otherEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => '090a',
        'notes' => 'Eerste vermelding in de oudere editie.',
    ]);

    $otherEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $otherEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => '190b',
        'notes' => 'Tweede vermelding in de oudere editie.',
    ]);

    // Maak cataloguswaarden aan in de oudere editie.
    $otherValueOne = $otherEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '12.50',
        'currency' => 'EUR',
        'notes' => 'Oudere postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    $otherValueTwo = $otherEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '4.00',
        'currency' => 'EUR',
        'notes' => 'Oudere gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Maak vermeldingen aan in de geheel andere catalogus.
    $otherCatalogEntryOne = StampCatalogEntry::create([
        'catalog_edition_id' => $otherCatalogEdition->id,
        'stamp_id' => $stampOne->id,
        'catalog_number' => 'X-100',
        'notes' => 'Eerste vermelding in de andere catalogus.',
    ]);

    $otherCatalogEntryTwo = StampCatalogEntry::create([
        'catalog_edition_id' => $otherCatalogEdition->id,
        'stamp_id' => $stampTwo->id,
        'catalog_number' => 'X-200',
        'notes' => 'Tweede vermelding in de andere catalogus.',
    ]);

    // Maak cataloguswaarden aan in de andere catalogus.
    $otherCatalogValueOne = $otherCatalogEntryOne->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '25.00',
        'currency' => 'USD',
        'notes' => 'Postfrisse waarde in de andere catalogus.',
        'needs_review' => true,
    ]);

    $otherCatalogValueTwo = $otherCatalogEntryTwo->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '7.50',
        'currency' => 'USD',
        'notes' => 'Gestempelde waarde in de andere catalogus.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke gegevens van beide catalogussen.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalOtherCatalog = $otherCatalog->fresh()->getAttributes();

    // Bewaar de oorspronkelijke gegevens van alle catalogusedities.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();
    $originalOtherEdition = $otherEdition->fresh()->getAttributes();
    $originalOtherCatalogEdition = $otherCatalogEdition->fresh()->getAttributes();

    // Bewaar de vermeldingen uit de oudere editie.
    $originalOtherEntryOne = $otherEntryOne->fresh()->getAttributes();
    $originalOtherEntryTwo = $otherEntryTwo->fresh()->getAttributes();

    // Bewaar de cataloguswaarden uit de oudere editie.
    $originalOtherValueOne = $otherValueOne->fresh()->getAttributes();
    $originalOtherValueTwo = $otherValueTwo->fresh()->getAttributes();

    // Bewaar de vermeldingen uit de andere catalogus.
    $originalOtherCatalogEntryOne = $otherCatalogEntryOne->fresh()->getAttributes();
    $originalOtherCatalogEntryTwo = $otherCatalogEntryTwo->fresh()->getAttributes();

    // Bewaar de cataloguswaarden uit de andere catalogus.
    $originalOtherCatalogValueOne = $otherCatalogValueOne->fresh()->getAttributes();
    $originalOtherCatalogValueTwo = $otherCatalogValueTwo->fresh()->getAttributes();

    // Bewaar alle bestaande catalogusvermeldingen.
    $originalEntries = StampCatalogEntry::query()
        ->where('catalog_edition_id', '!=', $this->targetEdition->id)
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle bestaande cataloguswaarden.
    $originalValues = StampCatalogValue::query()
        ->whereIn(
            'stamp_catalog_entry_id',
            StampCatalogEntry::query()
                ->where('catalog_edition_id', '!=', $this->targetEdition->id)
                ->select('id')
        )
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(2)
        ->and(CatalogEdition::count())->toBe(4)
        ->and(Stamp::count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(6)
        ->and(StampCatalogValue::count())->toBe(6);

    // Controleer de oorspronkelijke aantallen per editie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($otherEdition->stampEntries()->count())->toBe(2)
        ->and($otherEdition->stampValues()->count())->toBe(2)
        ->and($otherCatalogEdition->stampEntries()->count())->toBe(2)
        ->and($otherCatalogEdition->stampValues()->count())->toBe(2);

    // Verplaats de testtijd om ongewenste wijzigingen
    // aan timestamps zichtbaar te maken.
    $this->travel(1)->minutes();

    try {
        // Dupliceer uitsluitend de broneditie naar de bedoelde doeleditie.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true
        );

        // Controleer de teruggegeven aantallen.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 0,
        ]);

        // Controleer de algemene databaseaantallen.
        expect(Catalog::count())->toBe(2)
            ->and(CatalogEdition::count())->toBe(4)
            ->and(Stamp::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(8)
            ->and(StampCatalogValue::count())->toBe(8);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(2)
            ->and($otherEdition->stampEntries()->count())->toBe(2)
            ->and($otherEdition->stampValues()->count())->toBe(2)
            ->and($otherCatalogEdition->stampEntries()->count())->toBe(2)
            ->and($otherCatalogEdition->stampValues()->count())->toBe(2);

        // Controleer dat beide catalogussen ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalog)
            ->and($otherCatalog->fresh()->getAttributes())
            ->toBe($originalOtherCatalog);

        // Controleer dat de catalogusedities zelf ongewijzigd zijn.
        expect($this->sourceEdition->fresh()->getAttributes())
            ->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())
            ->toBe($originalTargetEdition)
            ->and($otherEdition->fresh()->getAttributes())
            ->toBe($originalOtherEdition)
            ->and($otherCatalogEdition->fresh()->getAttributes())
            ->toBe($originalOtherCatalogEdition);

        // Controleer de vermeldingen in de oudere editie.
        expect($otherEntryOne->fresh()->getAttributes())
            ->toBe($originalOtherEntryOne)
            ->and($otherEntryTwo->fresh()->getAttributes())
            ->toBe($originalOtherEntryTwo);

        // Controleer de cataloguswaarden in de oudere editie.
        expect($otherValueOne->fresh()->getAttributes())
            ->toBe($originalOtherValueOne)
            ->and($otherValueTwo->fresh()->getAttributes())
            ->toBe($originalOtherValueTwo);

        // Controleer de vermeldingen in de andere catalogus.
        expect($otherCatalogEntryOne->fresh()->getAttributes())
            ->toBe($originalOtherCatalogEntryOne)
            ->and($otherCatalogEntryTwo->fresh()->getAttributes())
            ->toBe($originalOtherCatalogEntryTwo);

        // Controleer de cataloguswaarden in de andere catalogus.
        expect($otherCatalogValueOne->fresh()->getAttributes())
            ->toBe($originalOtherCatalogValueOne)
            ->and($otherCatalogValueTwo->fresh()->getAttributes())
            ->toBe($originalOtherCatalogValueTwo);

        // Haal alle vermeldingen buiten de doeleditie opnieuw op.
        $currentEntries = StampCatalogEntry::query()
            ->where('catalog_edition_id', '!=', $this->targetEdition->id)
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all();

        // Haal alle cataloguswaarden buiten de doeleditie opnieuw op.
        $currentValues = StampCatalogValue::query()
            ->whereIn(
                'stamp_catalog_entry_id',
                StampCatalogEntry::query()
                    ->where('catalog_edition_id', '!=', $this->targetEdition->id)
                    ->select('id')
            )
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all();

        // Controleer dat alle gegevens buiten de doeleditie
        // volledig ongewijzigd zijn gebleven.
        expect($currentEntries)->toBe($originalEntries)
            ->and($currentValues)->toBe($originalValues);

        // Haal de nieuw gekopieerde doelvermeldingen op.
        $targetEntryOne = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampOne->id)
            ->firstOrFail();

        $targetEntryTwo = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stampTwo->id)
            ->firstOrFail();

        // Controleer dat uitsluitend de juiste brongegevens
        // naar de doeleditie zijn gekopieerd.
        expect($targetEntryOne->catalog_number)->toBe('100a')
            ->and($targetEntryOne->notes)->toBe('Eerste bronvermelding.')
            ->and($targetEntryTwo->catalog_number)->toBe('200b')
            ->and($targetEntryTwo->notes)->toBe('Tweede bronvermelding.');

        // Haal de nieuw gekopieerde cataloguswaarden op.
        $targetValueOne = $targetEntryOne->values()
            ->where('condition_id', $this->postfris->id)
            ->firstOrFail();

        $targetValueTwo = $targetEntryTwo->values()
            ->where('condition_id', $this->gestempeld->id)
            ->firstOrFail();

        // Controleer de juiste cataloguswaarden in de doeleditie.
        expect($targetValueOne->catalog_value)->toBe('15.75')
            ->and($targetValueOne->currency)->toBe('EUR')
            ->and($targetValueOne->notes)->toBe('Postfrisse bronwaarde.')
            ->and($targetValueOne->needs_review)->toBeTrue()
            ->and($targetValueTwo->catalog_value)->toBe('5.25')
            ->and($targetValueTwo->currency)->toBe('EUR')
            ->and($targetValueTwo->notes)->toBe('Gestempelde bronwaarde.')
            ->and($targetValueTwo->needs_review)->toBeTrue();

        // Controleer dat iedere doelvermelding precies één waarde heeft.
        expect($targetEntryOne->values()->count())->toBe(1)
            ->and($targetEntryTwo->values()->count())->toBe(1);
    } finally {
        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }
});
