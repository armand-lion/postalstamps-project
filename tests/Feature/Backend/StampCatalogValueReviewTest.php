<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;

beforeEach(function () {
    // Gebruik Nederlands als standaardtaal voor de tests.
    app()->setLocale('nl');

    // Maak een beheerder aan die cataloguswaarden mag controleren.
    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een land aan voor de functionele controletests.
    $this->country = Country::create([
        'name' => 'Testland Cataloguswaardecontrole',
        'iso_code' => 'CC',
        'entity_type' => 'country',
        'slug' => 'testland-cataloguswaardecontrole',
        'is_active' => true,
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Cataloguswaardecontrole',
        'abbreviation' => 'TCC',
    ]);

    // Koppel het land aan de catalogus.
    $this->catalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie aan.
    $this->edition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak een waarderingscategorie aan.
    $this->condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak een postzegel aan.
    $this->stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan.
    $this->catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'REVIEW-001',
        'notes' => 'Oorspronkelijke notities bij de catalogusvermelding.',
    ]);

    // Maak een cataloguswaarde aan die nog gecontroleerd moet worden.
    $this->catalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $this->catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
        'needs_review' => true,
    ]);
});

test('een niet-ingelogde gebruiker kan een bestaande cataloguswaarde niet controleren', function () {
    // Bewaar de oorspronkelijke databasegegevens.
    $originalCatalogValue = $this->catalogValue->fresh()->getAttributes();
    $originalCatalogEntry = $this->catalogEntry->fresh()->getAttributes();

    // Probeer zonder authenticatie de cataloguswaarde te controleren.
    $response = $this->patch(
        route('backend.stamp-catalog-values.review', $this->catalogValue)
    );

    // Controleer of de gebruiker naar de loginpagina wordt doorgestuurd.
    $response->assertRedirect(route('login'));

    // Controleer of er geen succesmelding is opgeslagen.
    $response->assertSessionMissing('success');

    // Controleer of de gebruiker niet is ingelogd.
    $this->assertGuest();

    // Controleer of de controlemarkering actief is gebleven.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of alle oorspronkelijke gegevens behouden zijn gebleven.
    expect($this->catalogValue->fresh()->getAttributes())
        ->toBe($originalCatalogValue);

    expect($this->catalogEntry->fresh()->getAttributes())
        ->toBe($originalCatalogEntry);

    // Controleer of er geen catalogusvermeldingen of cataloguswaarden
    // zijn toegevoegd of verwijderd.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een beheerder ontvangt de juiste redirect en succesmelding na een controlebewerking', function () {
    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Controleer de oorspronkelijke controlemarkering.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Voer de controlebewerking uit als beheerder.
    $response = $this->actingAs($this->admin)
        ->from($reviewUrl)
        ->patch(
            route('backend.stamp-catalog-values.review', $this->catalogValue)
        );

    // Controleer de redirect naar de oorspronkelijke controlepagina.
    $response->assertRedirect($reviewUrl);

    // Controleer de exacte succesmelding.
    $response->assertSessionHas(
        'success',
        'De cataloguswaarde is als gecontroleerd gemarkeerd.'
    );

    // Controleer of er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer of de beheerder ingelogd is gebleven.
    $this->assertAuthenticatedAs($this->admin);

    // Controleer of de cataloguswaarde daadwerkelijk gecontroleerd is.
    expect($this->catalogValue->fresh()->needs_review)->toBeFalse();

    // Controleer of de catalogusvermelding en cataloguswaarde
    // nog steeds aanwezig zijn.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('de controlebewerking wijzigt uitsluitend de controlemarkering en de wijzigingstijdstempel', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalAttributes = $originalCatalogValue->getAttributes();

    // Controleer de oorspronkelijke controlemarkering.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Verplaats de testtijd om een wijziging van updated_at
    // betrouwbaar te kunnen controleren.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
        $response = $this->actingAs($this->admin)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de bewerking succesvol is uitgevoerd.
        $response->assertRedirect();

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        // Haal de gewijzigde cataloguswaarde opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();

        // Controleer de nieuwe controlemarkering.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of de wijzigingstijdstempel is bijgewerkt.
        expect(
            $reviewedCatalogValue->updated_at->greaterThan(
                $originalCatalogValue->updated_at
            )
        )->toBeTrue();

        // Controleer afzonderlijk de belangrijkste databasevelden.
        expect($reviewedCatalogValue->id)
            ->toBe($originalCatalogValue->id)
            ->and($reviewedCatalogValue->stamp_catalog_entry_id)
            ->toBe($originalCatalogValue->stamp_catalog_entry_id)
            ->and($reviewedCatalogValue->condition_id)
            ->toBe($originalCatalogValue->condition_id)
            ->and($reviewedCatalogValue->catalog_value)
            ->toBe('12.50')
            ->and($reviewedCatalogValue->currency)
            ->toBe('EUR')
            ->and($reviewedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Haal alle gewijzigde databaseattributen op.
        $reviewedAttributes = $reviewedCatalogValue->getAttributes();

        // Verwijder uitsluitend de twee velden die mogen veranderen.
        unset(
            $originalAttributes['needs_review'],
            $originalAttributes['updated_at'],
            $reviewedAttributes['needs_review'],
            $reviewedAttributes['updated_at']
        );

        // Controleer of alle overige databaseattributen
        // volledig ongewijzigd zijn gebleven.
        expect($reviewedAttributes)->toBe($originalAttributes);

        // Controleer of er geen catalogusvermeldingen of
        // cataloguswaarden zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de bijbehorende catalogusvermelding blijft volledig ongewijzigd na een controlebewerking', function () {
    // Haal de oorspronkelijke catalogusvermelding op.
    $originalCatalogEntry = $this->catalogEntry->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalAttributes = $originalCatalogEntry->getAttributes();

    // Controleer de oorspronkelijke controlemarkering.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempel te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
        $response = $this->actingAs($this->admin)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de controlebewerking succesvol is uitgevoerd.
        $response->assertRedirect();

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        // Haal de cataloguswaarde en catalogusvermelding opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();
        $catalogEntryAfterReview = $this->catalogEntry->fresh();

        // Controleer of de cataloguswaarde daadwerkelijk gecontroleerd is.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of de koppeling met de catalogusvermelding behouden is.
        expect($reviewedCatalogValue->stamp_catalog_entry_id)
            ->toBe($originalCatalogEntry->id);

        // Controleer afzonderlijk de belangrijkste databasevelden.
        expect($catalogEntryAfterReview->id)
            ->toBe($originalCatalogEntry->id)
            ->and($catalogEntryAfterReview->catalog_edition_id)
            ->toBe($this->edition->id)
            ->and($catalogEntryAfterReview->stamp_id)
            ->toBe($this->stamp->id)
            ->and($catalogEntryAfterReview->catalog_number)
            ->toBe('REVIEW-001')
            ->and($catalogEntryAfterReview->notes)
            ->toBe('Oorspronkelijke notities bij de catalogusvermelding.');

        // Controleer of alle databaseattributen van de
        // catalogusvermelding volledig ongewijzigd zijn gebleven.
        expect($catalogEntryAfterReview->getAttributes())
            ->toBe($originalAttributes);

        // Controleer of de relatie met de cataloguswaarde behouden is.
        expect($catalogEntryAfterReview->values()->count())->toBe(1);

        expect($catalogEntryAfterReview->values()->first()->id)
            ->toBe($reviewedCatalogValue->id);

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de bijbehorende waarderingscategorie blijft volledig ongewijzigd na een controlebewerking', function () {
    // Haal de oorspronkelijke waarderingscategorie op.
    $originalCondition = $this->condition->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalAttributes = $originalCondition->getAttributes();

    // Controleer de oorspronkelijke controlemarkering.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempel te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
        $response = $this->actingAs($this->admin)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de controlebewerking succesvol is uitgevoerd.
        $response->assertRedirect();

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        // Haal de cataloguswaarde en waarderingscategorie opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();
        $conditionAfterReview = $this->condition->fresh();

        // Controleer of de cataloguswaarde daadwerkelijk gecontroleerd is.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of de koppeling met de waarderingscategorie behouden is.
        expect($reviewedCatalogValue->condition_id)
            ->toBe($originalCondition->id);

        // Controleer afzonderlijk de belangrijkste databasevelden.
        expect($conditionAfterReview->id)
            ->toBe($originalCondition->id)
            ->and($conditionAfterReview->slug)
            ->toBe('postfris')
            ->and((int) $conditionAfterReview->sort_order)
            ->toBe(1)
            ->and((bool) $conditionAfterReview->is_active)
            ->toBeTrue();

        // Controleer of alle databaseattributen van de
        // waarderingscategorie volledig ongewijzigd zijn gebleven.
        expect($conditionAfterReview->getAttributes())
            ->toBe($originalAttributes);

        // Controleer of de modelrelatie met de waarderingscategorie
        // nog steeds correct functioneert.
        expect($reviewedCatalogValue->condition->id)
            ->toBe($originalCondition->id);

        expect($reviewedCatalogValue->condition->slug)
            ->toBe('postfris');

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een al gecontroleerde cataloguswaarde blijft volledig ongewijzigd', function () {
    // Bereid een cataloguswaarde voor die al gecontroleerd is.
    $this->catalogValue->update([
        'needs_review' => false,
    ]);

    // Haal de oorspronkelijke databaseobjecten op.
    $originalCatalogValue = $this->catalogValue->fresh();
    $originalCatalogEntry = $this->catalogEntry->fresh();
    $originalCondition = $this->condition->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();
    $originalEntryAttributes = $originalCatalogEntry->getAttributes();
    $originalConditionAttributes = $originalCondition->getAttributes();

    // Controleer of de cataloguswaarde al gecontroleerd is.
    expect($originalCatalogValue->needs_review)->toBeFalse();

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempels te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer de al gecontroleerde cataloguswaarde opnieuw te controleren.
        $response = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de oorspronkelijke redirect behouden blijft.
        $response->assertRedirect($reviewUrl);

        // Controleer of de oorspronkelijke succesmelding behouden blijft.
        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        // Controleer of er geen validatiefouten zijn.
        $response->assertSessionHasNoErrors();

        // Haal de databaseobjecten opnieuw op.
        $catalogValueAfterReview = $this->catalogValue->fresh();
        $catalogEntryAfterReview = $this->catalogEntry->fresh();
        $conditionAfterReview = $this->condition->fresh();

        // Controleer of de controlemarkering inactief is gebleven.
        expect($catalogValueAfterReview->needs_review)->toBeFalse();

        // Controleer afzonderlijk de belangrijkste gegevens.
        expect($catalogValueAfterReview->catalog_value)
            ->toBe('12.50')
            ->and($catalogValueAfterReview->currency)
            ->toBe('EUR')
            ->and($catalogValueAfterReview->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of alle databaseattributen van de cataloguswaarde
        // volledig ongewijzigd zijn gebleven.
        expect($catalogValueAfterReview->getAttributes())
            ->toBe($originalValueAttributes);

        // Controleer of de wijzigingstijdstempel ongewijzigd is gebleven.
        expect(
            $catalogValueAfterReview->updated_at->equalTo(
                $originalCatalogValue->updated_at
            )
        )->toBeTrue();

        // Controleer of de catalogusvermelding volledig ongewijzigd is.
        expect($catalogEntryAfterReview->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de waarderingscategorie volledig ongewijzigd is.
        expect($conditionAfterReview->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('conditions', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een herhaalde controlebewerking veroorzaakt geen aanvullende wijzigingen', function () {
    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Bepaal de route voor de controlebewerking.
    $reviewActionUrl = route(
        'backend.stamp-catalog-values.review',
        $this->catalogValue
    );

    // Haal de oorspronkelijke databaseobjecten op.
    $originalCatalogValue = $this->catalogValue->fresh();

    $originalEntryAttributes = $this->catalogEntry->fresh()->getAttributes();
    $originalConditionAttributes = $this->condition->fresh()->getAttributes();

    // Controleer de oorspronkelijke controlemarkering.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Verplaats de testtijd voor de eerste controlebewerking.
    $this->travel(1)->minutes();

    try {
        // Voer de eerste controlebewerking uit als beheerder.
        $firstResponse = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch($reviewActionUrl);

        // Controleer het resultaat van de eerste controlebewerking.
        $firstResponse->assertRedirect($reviewUrl);

        $firstResponse->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $firstResponse->assertSessionHasNoErrors();

        // Haal de cataloguswaarde na de eerste controlebewerking op.
        $catalogValueAfterFirstReview = $this->catalogValue->fresh();

        // Controleer of de controlemarkering is uitgeschakeld.
        expect($catalogValueAfterFirstReview->needs_review)->toBeFalse();

        // Controleer of de wijzigingstijdstempel is bijgewerkt.
        expect(
            $catalogValueAfterFirstReview->updated_at->greaterThan(
                $originalCatalogValue->updated_at
            )
        )->toBeTrue();

        // Bewaar alle databaseattributen na de eerste controlebewerking.
        $attributesAfterFirstReview = $catalogValueAfterFirstReview
            ->getAttributes();

        // Verplaats de testtijd opnieuw om eventuele aanvullende
        // wijzigingen van de wijzigingstijdstempel te detecteren.
        $this->travel(1)->minutes();

        // Voer dezelfde controlebewerking opnieuw uit.
        $secondResponse = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch($reviewActionUrl);

        // Controleer het resultaat van de tweede controlebewerking.
        $secondResponse->assertRedirect($reviewUrl);

        $secondResponse->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $secondResponse->assertSessionHasNoErrors();

        // Haal de cataloguswaarde na de tweede controlebewerking op.
        $catalogValueAfterSecondReview = $this->catalogValue->fresh();

        // Controleer of de controlemarkering inactief is gebleven.
        expect($catalogValueAfterSecondReview->needs_review)->toBeFalse();

        // Controleer of alle databaseattributen sinds de eerste
        // controlebewerking volledig ongewijzigd zijn gebleven.
        expect($catalogValueAfterSecondReview->getAttributes())
            ->toBe($attributesAfterFirstReview);

        // Controleer afzonderlijk de wijzigingstijdstempel.
        expect(
            $catalogValueAfterSecondReview->updated_at->equalTo(
                $catalogValueAfterFirstReview->updated_at
            )
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde,
        // valuta en notities behouden zijn gebleven.
        expect($catalogValueAfterSecondReview->catalog_value)
            ->toBe('12.50')
            ->and($catalogValueAfterSecondReview->currency)
            ->toBe('EUR')
            ->and($catalogValueAfterSecondReview->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of de catalogusvermelding ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de waarderingscategorie ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de beheerder ingelogd is gebleven.
        $this->assertAuthenticatedAs($this->admin);

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('conditions', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('cataloguswaarden van andere postzegels blijven volledig ongewijzigd na een controlebewerking', function () {
    // Maak een tweede postzegel aan binnen hetzelfde land.
    $otherStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan voor de tweede postzegel
    // binnen dezelfde cataloguseditie.
    $otherCatalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $otherStamp->id,
        'catalog_number' => 'REVIEW-002',
        'notes' => 'Notities bij de tweede catalogusvermelding.',
    ]);

    // Maak een cataloguswaarde aan voor de tweede postzegel
    // met dezelfde waarderingscategorie.
    $otherCatalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $otherCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 25.00,
        'currency' => 'EUR',
        'notes' => 'Notities bij de tweede cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke databaseattributen
    // van de tweede postzegel en haar catalogusgegevens.
    $originalOtherStampAttributes = $otherStamp->fresh()->getAttributes();

    $originalOtherEntryAttributes = $otherCatalogEntry
        ->fresh()
        ->getAttributes();

    $originalOtherValueAttributes = $otherCatalogValue
        ->fresh()
        ->getAttributes();

    // Bewaar ook de oorspronkelijke catalogusvermelding
    // van de geselecteerde postzegel.
    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    // Controleer of beide cataloguswaarden nog gecontroleerd moeten worden.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    expect($otherCatalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of beide cataloguswaarden tot dezelfde
    // waarderingscategorie behoren.
    expect($otherCatalogValue->condition_id)
        ->toBe($this->catalogValue->condition_id);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempels te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Controleer uitsluitend de cataloguswaarde van de eerste postzegel.
        $response = $this->actingAs($this->admin)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de controlebewerking succesvol is uitgevoerd.
        $response->assertRedirect();

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $response->assertSessionHasNoErrors();

        // Haal beide cataloguswaarden opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();
        $otherCatalogValueAfterReview = $otherCatalogValue->fresh();

        // Controleer of uitsluitend de geselecteerde cataloguswaarde
        // als gecontroleerd is gemarkeerd.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        expect($otherCatalogValueAfterReview->needs_review)->toBeTrue();

        // Controleer de belangrijkste gegevens van de tweede cataloguswaarde.
        expect($otherCatalogValueAfterReview->id)
            ->toBe($otherCatalogValue->id)
            ->and($otherCatalogValueAfterReview->stamp_catalog_entry_id)
            ->toBe($otherCatalogEntry->id)
            ->and($otherCatalogValueAfterReview->condition_id)
            ->toBe($this->condition->id)
            ->and($otherCatalogValueAfterReview->catalog_value)
            ->toBe('25.00')
            ->and($otherCatalogValueAfterReview->currency)
            ->toBe('EUR')
            ->and($otherCatalogValueAfterReview->notes)
            ->toBe('Notities bij de tweede cataloguswaarde.');

        // Controleer of alle databaseattributen van de tweede
        // cataloguswaarde volledig ongewijzigd zijn gebleven.
        expect($otherCatalogValueAfterReview->getAttributes())
            ->toBe($originalOtherValueAttributes);

        // Controleer of de tweede catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($otherCatalogEntry->fresh()->getAttributes())
            ->toBe($originalOtherEntryAttributes);

        // Controleer of de tweede postzegel
        // volledig ongewijzigd is gebleven.
        expect($otherStamp->fresh()->getAttributes())
            ->toBe($originalOtherStampAttributes);

        // Controleer of ook de catalogusvermelding van de
        // geselecteerde postzegel ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($reviewedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamps', 2);
        $this->assertDatabaseCount('stamp_catalog_entries', 2);
        $this->assertDatabaseCount('stamp_catalog_values', 2);
        $this->assertDatabaseCount('conditions', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('cataloguswaarden in andere catalogusedities blijven volledig ongewijzigd na een controlebewerking', function () {
    // Maak een tweede cataloguseditie aan binnen dezelfde catalogus.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'currency' => 'EUR',
    ]);

    // Maak een catalogusvermelding aan voor dezelfde postzegel
    // binnen de tweede cataloguseditie.
    $otherCatalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $otherEdition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'REVIEW-001',
        'notes' => 'Notities bij de catalogusvermelding van editie 2027.',
    ]);

    // Maak een cataloguswaarde aan binnen de tweede cataloguseditie
    // met dezelfde waarderingscategorie.
    $otherCatalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $otherCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 35.00,
        'currency' => 'EUR',
        'notes' => 'Notities bij de cataloguswaarde van editie 2027.',
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke databaseattributen
    // van de tweede cataloguseditie.
    $originalOtherEditionAttributes = $otherEdition
        ->fresh()
        ->getAttributes();

    $originalOtherEntryAttributes = $otherCatalogEntry
        ->fresh()
        ->getAttributes();

    $originalOtherValueAttributes = $otherCatalogValue
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke gegevens van de eerste
    // cataloguseditie en catalogusvermelding.
    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke gegevens van de gedeelde objecten.
    $originalCatalogAttributes = $this->catalog->fresh()->getAttributes();
    $originalStampAttributes = $this->stamp->fresh()->getAttributes();
    $originalConditionAttributes = $this->condition->fresh()->getAttributes();

    // Controleer of beide cataloguswaarden nog gecontroleerd moeten worden.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    expect($otherCatalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of beide catalogusvermeldingen bij dezelfde
    // postzegel horen, maar bij verschillende catalogusedities.
    expect($otherCatalogEntry->stamp_id)
        ->toBe($this->catalogEntry->stamp_id);

    expect($otherCatalogEntry->catalog_edition_id)
        ->not->toBe($this->catalogEntry->catalog_edition_id);

    // Controleer of beide cataloguswaarden dezelfde
    // waarderingscategorie gebruiken.
    expect($otherCatalogValue->condition_id)
        ->toBe($this->catalogValue->condition_id);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempels te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Controleer uitsluitend de cataloguswaarde van editie 2026.
        $response = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de controlebewerking succesvol is uitgevoerd.
        $response->assertRedirect($reviewUrl);

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $response->assertSessionHasNoErrors();

        // Haal beide cataloguswaarden opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();
        $otherCatalogValueAfterReview = $otherCatalogValue->fresh();

        // Controleer of uitsluitend de cataloguswaarde van
        // editie 2026 als gecontroleerd is gemarkeerd.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        expect($otherCatalogValueAfterReview->needs_review)->toBeTrue();

        // Controleer of de cataloguswaarde van editie 2026 behouden is.
        expect($reviewedCatalogValue->catalog_value)
            ->toBe('12.50')
            ->and($reviewedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer de belangrijkste gegevens van de cataloguswaarde
        // binnen editie 2027.
        expect($otherCatalogValueAfterReview->id)
            ->toBe($otherCatalogValue->id)
            ->and($otherCatalogValueAfterReview->stamp_catalog_entry_id)
            ->toBe($otherCatalogEntry->id)
            ->and($otherCatalogValueAfterReview->condition_id)
            ->toBe($this->condition->id)
            ->and($otherCatalogValueAfterReview->catalog_value)
            ->toBe('35.00')
            ->and($otherCatalogValueAfterReview->currency)
            ->toBe('EUR')
            ->and($otherCatalogValueAfterReview->notes)
            ->toBe('Notities bij de cataloguswaarde van editie 2027.');

        // Controleer of alle databaseattributen van de cataloguswaarde
        // binnen editie 2027 volledig ongewijzigd zijn gebleven.
        expect($otherCatalogValueAfterReview->getAttributes())
            ->toBe($originalOtherValueAttributes);

        // Controleer of de catalogusvermelding van editie 2027
        // volledig ongewijzigd is gebleven.
        expect($otherCatalogEntry->fresh()->getAttributes())
            ->toBe($originalOtherEntryAttributes);

        // Controleer of editie 2027 volledig ongewijzigd is gebleven.
        expect($otherEdition->fresh()->getAttributes())
            ->toBe($originalOtherEditionAttributes);

        // Controleer of de catalogusvermelding van editie 2026
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of editie 2026 volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de gedeelde catalogus ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de gedeelde postzegel ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de gedeelde waarderingscategorie
        // ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of er geen records zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 2);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 2);
        $this->assertDatabaseCount('stamp_catalog_values', 2);
        $this->assertDatabaseCount('conditions', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});
