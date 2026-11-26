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

beforeEach(function () {
    // Gebruik Nederlands als standaardtaal voor de tests.
    app()->setLocale('nl');

    // Maak een beheerder aan die cataloguswaarden mag controleren.
    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een gewone gebruiker aan voor de autorisatietests.
    $this->user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een land aan voor de geschiedenisregistratietests.
    $this->country = Country::create([
        'name' => 'Testland Controlebewerkingsgeschiedenis',
        'iso_code' => 'CG',
        'entity_type' => 'country',
        'slug' => 'testland-controlebewerkingsgeschiedenis',
        'is_active' => true,
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Controlebewerkingsgeschiedenis',
        'abbreviation' => 'TCG',
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
        'catalog_number' => 'REVIEW-HISTORY-001',
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

test('een succesvolle controlebewerking maakt precies één geschiedenisbatch aan', function () {
    // Controleer de oorspronkelijke controlemarkering.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of er vooraf nog geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Voer de controlebewerking uit als beheerder.
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

    // Controleer of de cataloguswaarde daadwerkelijk
    // als gecontroleerd is gemarkeerd.
    $reviewedCatalogValue = $this->catalogValue->fresh();

    expect($reviewedCatalogValue->needs_review)->toBeFalse();

    // Controleer of de oorspronkelijke cataloguswaarde behouden is.
    expect($reviewedCatalogValue->catalog_value)
        ->toBe('12.50')
        ->and($reviewedCatalogValue->currency)
        ->toBe('EUR')
        ->and($reviewedCatalogValue->notes)
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of precies één geschiedenisbatch
    // en één geschiedenisregel zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer het type controlebewerking.
    expect($batch->operation)->toBe('review');

    // Controleer de omschrijving van de controlebewerking.
    expect($batch->description)
        ->toBe('Cataloguswaarde als gecontroleerd gemarkeerd');

    // Controleer of de geschiedenisbatch bij de juiste
    // cataloguseditie hoort.
    expect($batch->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer of de geschiedenisbatch precies één
    // gekoppelde geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Haal de aangemaakte geschiedenisregel op.
    $log = CatalogChangeLog::query()->sole();

    // Controleer of de geschiedenisregel aan de juiste
    // geschiedenisbatch is gekoppeld.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer of de relatie vanuit de geschiedenisbatch
    // naar de geschiedenisregel correct functioneert.
    expect($batch->logs()->sole()->id)
        ->toBe($log->id);

    // Controleer of de omgekeerde relatie correct functioneert.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer of de geschiedenisbatch daadwerkelijk
    // in de database is opgeslagen.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'operation' => 'review',
        'description' => 'Cataloguswaarde als gecontroleerd gemarkeerd',
    ]);

    // Controleer of de geschiedenisregel daadwerkelijk
    // aan de geschiedenisbatch is gekoppeld in de database.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
    ]);

    // Controleer of er geen ongewenste wijzigingen zijn
    // in het aantal catalogusgerelateerde records.
    $this->assertDatabaseCount('catalogs', 1);
    $this->assertDatabaseCount('catalog_editions', 1);
    $this->assertDatabaseCount('stamps', 1);
    $this->assertDatabaseCount('conditions', 1);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('de geschiedenisbatch bevat de juiste beheerder en gebruikersnaam', function () {
    // Geef beide gebruikers herkenbare en verschillende namen.
    $this->admin->update([
        'name' => 'Beheerder Cataloguswaardecontrole',
    ]);

    $this->user->update([
        'name' => 'Gewone Gebruiker Cataloguswaardecontrole',
    ]);

    // Bewaar de oorspronkelijke gebruikersgegevens.
    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    // Controleer of beide gebruikers verschillende gebruikers-ID's hebben.
    expect($this->admin->id)->not->toBe($this->user->id);

    // Controleer de oorspronkelijke autorisatiegegevens.
    expect($this->admin->is_admin)->toBeTrue();

    expect($this->user->is_admin)->toBeFalse();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Voer de controlebewerking uit als beheerder.
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

    // Controleer of de beheerder de ingelogde gebruiker is.
    $this->assertAuthenticatedAs($this->admin);

    // Controleer of de cataloguswaarde als gecontroleerd is gemarkeerd.
    expect($this->catalogValue->fresh()->needs_review)->toBeFalse();

    // Controleer of precies één geschiedenisbatch
    // en één geschiedenisregel zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer of de juiste beheerder is geregistreerd.
    expect($batch->user_id)->toBe($this->admin->id);

    // Controleer of de oorspronkelijke gebruikersnaam is opgeslagen.
    expect($batch->user_name)
        ->toBe('Beheerder Cataloguswaardecontrole');

    // Controleer of de gewone gebruiker niet is geregistreerd
    // als verantwoordelijke voor de controlebewerking.
    expect($batch->user_id)->not->toBe($this->user->id);

    expect($batch->user_name)->not->toBe($this->user->name);

    // Controleer of de gebruikersrelatie correct functioneert.
    expect($batch->user)->toBeInstanceOf(User::class);

    expect($batch->user->id)->toBe($this->admin->id);

    expect($batch->user->name)
        ->toBe('Beheerder Cataloguswaardecontrole');

    expect($batch->user->is_admin)->toBeTrue();

    // Controleer of de gebruikersgegevens correct
    // in de database zijn opgeslagen.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'user_id' => $this->admin->id,
        'user_name' => 'Beheerder Cataloguswaardecontrole',
        'operation' => 'review',
    ]);

    // Controleer of er geen geschiedenisbatch voor
    // de gewone gebruiker is aangemaakt.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'user_id' => $this->user->id,
    ]);

    // Controleer of de geschiedenisregel bij de juiste batch hoort.
    $log = CatalogChangeLog::query()->sole();

    expect($log->catalog_change_batch_id)->toBe($batch->id);

    expect($log->batch->user_id)->toBe($this->admin->id);

    expect($log->batch->user_name)
        ->toBe('Beheerder Cataloguswaardecontrole');

    // Controleer of beide gebruikers volledig ongewijzigd zijn gebleven.
    expect($this->admin->fresh()->getAttributes())
        ->toBe($originalAdminAttributes);

    expect($this->user->fresh()->getAttributes())
        ->toBe($originalUserAttributes);

    // Controleer of er geen gebruikers of catalogusrecords
    // zijn toegevoegd of verwijderd.
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('catalogs', 1);
    $this->assertDatabaseCount('catalog_editions', 1);
    $this->assertDatabaseCount('stamps', 1);
    $this->assertDatabaseCount('conditions', 1);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('de geschiedenisbatch bevat de juiste catalogus- en editiegegevens', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere Testcatalogus Controlebewerkingsgeschiedenis',
        'abbreviation' => 'ATCG',
    ]);

    // Koppel hetzelfde land aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie aan binnen de tweede catalogus.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'currency' => 'EUR',
    ]);

    // Bewaar alle oorspronkelijke databaseattributen
    // van beide catalogi en catalogusedities.
    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalOtherCatalogAttributes = $otherCatalog
        ->fresh()
        ->getAttributes();

    $originalOtherEditionAttributes = $otherEdition
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke catalogusvermelding.
    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    // Controleer of beide catalogi verschillende ID's hebben.
    expect($this->catalog->id)->not->toBe($otherCatalog->id);

    // Controleer of beide catalogusedities verschillende ID's hebben.
    expect($this->edition->id)->not->toBe($otherEdition->id);

    // Controleer of beide catalogusedities bij de juiste catalogus horen.
    expect($this->edition->catalog_id)
        ->toBe($this->catalog->id);

    expect($otherEdition->catalog_id)
        ->toBe($otherCatalog->id);

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van de wijzigingstijdstempels te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
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

        // Controleer of de cataloguswaarde als gecontroleerd is gemarkeerd.
        expect($this->catalogValue->fresh()->needs_review)->toBeFalse();

        // Controleer of precies één geschiedenisbatch
        // en één geschiedenisregel zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de aangemaakte geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer of de juiste cataloguseditie is geregistreerd.
        expect($batch->catalog_edition_id)
            ->toBe($this->edition->id);

        // Controleer de opgeslagen catalogusnaam.
        expect($batch->catalog_name)
            ->toBe('Testcatalogus Controlebewerkingsgeschiedenis');

        // Controleer de opgeslagen cataloguseditienaam.
        expect($batch->catalog_edition_name)
            ->toBe('Editie 2026');

        // Controleer of de tweede catalogus en cataloguseditie
        // niet ten onrechte zijn geregistreerd.
        expect($batch->catalog_edition_id)
            ->not->toBe($otherEdition->id);

        expect($batch->catalog_name)
            ->not->toBe($otherCatalog->name);

        expect($batch->catalog_edition_name)
            ->not->toBe($otherEdition->edition_name);

        // Controleer of de cataloguseditierelatie correct functioneert.
        expect($batch->catalogEdition)
            ->toBeInstanceOf(CatalogEdition::class);

        expect($batch->catalogEdition->id)
            ->toBe($this->edition->id);

        expect($batch->catalogEdition->edition_name)
            ->toBe('Editie 2026');

        expect($batch->catalogEdition->catalog_id)
            ->toBe($this->catalog->id);

        // Controleer de catalogusrelatie via de cataloguseditie.
        expect($batch->catalogEdition->catalog)
            ->toBeInstanceOf(Catalog::class);

        expect($batch->catalogEdition->catalog->id)
            ->toBe($this->catalog->id);

        expect($batch->catalogEdition->catalog->name)
            ->toBe('Testcatalogus Controlebewerkingsgeschiedenis');

        // Controleer of de catalogus- en editiegegevens
        // correct in de database zijn opgeslagen.
        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->edition->id,
            'catalog_name' => 'Testcatalogus Controlebewerkingsgeschiedenis',
            'catalog_edition_name' => 'Editie 2026',
            'operation' => 'review',
        ]);

        // Controleer of er geen geschiedenisbatch voor
        // de tweede cataloguseditie is aangemaakt.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $otherEdition->id,
        ]);

        // Haal de aangemaakte geschiedenisregel op.
        $log = CatalogChangeLog::query()->sole();

        // Controleer of de geschiedenisregel bij de juiste batch hoort.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        // Controleer of de geschiedenisregel eveneens
        // bij de juiste cataloguseditie hoort.
        expect($log->catalog_edition_id)
            ->toBe($this->edition->id);

        expect($log->catalog_edition_id)
            ->not->toBe($otherEdition->id);

        // Controleer de catalogusgegevens via de geschiedenisbatch.
        expect($log->batch->catalog_name)
            ->toBe('Testcatalogus Controlebewerkingsgeschiedenis');

        expect($log->batch->catalog_edition_name)
            ->toBe('Editie 2026');

        // Controleer of er geen geschiedenisregel voor
        // de tweede cataloguseditie is aangemaakt.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $otherEdition->id,
        ]);

        // Controleer of de oorspronkelijke catalogus
        // volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de oorspronkelijke cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de tweede catalogus
        // volledig ongewijzigd is gebleven.
        expect($otherCatalog->fresh()->getAttributes())
            ->toBe($originalOtherCatalogAttributes);

        // Controleer of de tweede cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($otherEdition->fresh()->getAttributes())
            ->toBe($originalOtherEditionAttributes);

        // Controleer of de oorspronkelijke catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($this->catalogValue->fresh()->catalog_value)
            ->toBe('12.50');

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('catalogs', 2);
        $this->assertDatabaseCount('catalog_editions', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een controlebewerking registreert de juiste geschiedenisregel', function () {
    // Bewaar de oorspronkelijke databaseattributen.
    $originalValueAttributes = $this->catalogValue
        ->fresh()
        ->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($this->catalogValue->fresh()->needs_review)->toBeTrue();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om wijzigingen van tijdstempels
    // betrouwbaar te kunnen controleren.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
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

        // Haal de bijgewerkte cataloguswaarde op.
        $reviewedCatalogValue = $this->catalogValue->fresh();

        // Controleer de nieuwe controlemarkering.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of precies één geschiedenisbatch
        // en één geschiedenisregel zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de geschiedenisbatch en geschiedenisregel op.
        $batch = CatalogChangeBatch::query()->sole();

        $log = CatalogChangeLog::query()->sole();

        // Controleer de koppeling met de geschiedenisbatch.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        // Controleer de gekoppelde cataloguseditie.
        expect($log->catalog_edition_id)
            ->toBe($this->edition->id);

        // Controleer de gekoppelde postzegel.
        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        // Controleer of er geen postzegelserie is gekoppeld.
        expect($log->stamp_series_id)->toBeNull();

        // Controleer de gekoppelde waarderingscategorie.
        expect($log->condition_id)
            ->toBe($this->condition->id);

        // Controleer het geregistreerde recordtype.
        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        // Controleer het ID van de gewijzigde cataloguswaarde.
        expect($log->record_id)
            ->toBe($this->catalogValue->id);

        // Controleer het geregistreerde actietype.
        expect($log->action)->toBe('updated');

        // Controleer of de oude en nieuwe waarden
        // als arrays beschikbaar zijn.
        expect($log->old_values)->toBeArray();

        expect($log->new_values)->toBeArray();

        // Controleer of de controlemarkering in beide arrays aanwezig is.
        expect($log->old_values)->toHaveKey('needs_review');

        expect($log->new_values)->toHaveKey('needs_review');

        // Controleer de oude controlemarkering.
        expect(in_array(
            $log->old_values['needs_review'],
            [true, 1],
            true
        ))->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(in_array(
            $log->new_values['needs_review'],
            [false, 0],
            true
        ))->toBeTrue();

        // Controleer of de oude controlemarkering overeenkomt
        // met de oorspronkelijke databasewaarde.
        expect((bool) $log->old_values['needs_review'])
            ->toBe((bool) $originalValueAttributes['needs_review']);

        // Controleer of de nieuwe controlemarkering overeenkomt
        // met de bijgewerkte databasewaarde.
        expect((bool) $log->new_values['needs_review'])
            ->toBe($reviewedCatalogValue->needs_review);

        // Controleer de relatie naar de geschiedenisbatch.
        expect($log->batch)
            ->toBeInstanceOf(CatalogChangeBatch::class);

        expect($log->batch->id)->toBe($batch->id);

        // Controleer de relatie naar de cataloguseditie.
        expect($log->catalogEdition)
            ->toBeInstanceOf(CatalogEdition::class);

        expect($log->catalogEdition->id)
            ->toBe($this->edition->id);

        // Controleer de relatie naar de postzegel.
        expect($log->stamp)
            ->toBeInstanceOf(Stamp::class);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        // Controleer of de postzegelserierelatie leeg is.
        expect($log->stampSeries)->toBeNull();

        // Controleer de relatie naar de waarderingscategorie.
        expect($log->condition)
            ->toBeInstanceOf(Condition::class);

        expect($log->condition->id)
            ->toBe($this->condition->id);

        // Controleer de omgekeerde relatie vanuit de geschiedenisbatch.
        expect($batch->logs()->sole()->id)
            ->toBe($log->id);

        // Controleer of de geschiedenisregel correct
        // in de database is opgeslagen.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->edition->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->condition->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
            'action' => 'updated',
        ]);

        // Controleer of er geen geschiedenisregel voor
        // de catalogusvermelding is aangemaakt.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Controleer of er geen geschiedenisregel voor
        // een postzegelseriewaarde is aangemaakt.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'series_catalog_value',
        ]);

        // Controleer of er geen aanmaakactie is geregistreerd.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        // Controleer of er geen verwijderactie is geregistreerd.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($reviewedCatalogValue->catalog_value)
            ->toBe('12.50');

        expect($reviewedCatalogValue->currency)
            ->toBe('EUR');

        expect($reviewedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('uitsluitend het gewijzigde veld needs_review wordt geregistreerd', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalAttributes = $originalCatalogValue->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer de oorspronkelijke controlemarkering.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd zodat updated_at daadwerkelijk verandert.
    $this->travel(1)->minutes();

    try {
        // Voer de controlebewerking uit als beheerder.
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

        // Haal de bijgewerkte cataloguswaarde op.
        $reviewedCatalogValue = $this->catalogValue->fresh();

        // Controleer de nieuwe controlemarkering.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of updated_at daadwerkelijk is gewijzigd.
        expect(
            $reviewedCatalogValue->updated_at->greaterThan($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of precies één geschiedenisbatch
        // en één geschiedenisregel zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de aangemaakte geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Haal de aangemaakte geschiedenisregel op.
        $log = CatalogChangeLog::query()->sole();

        // Controleer de koppeling met de geschiedenisbatch.
        expect($log->catalog_change_batch_id)->toBe($batch->id);

        // Controleer het geregistreerde recordtype.
        expect($log->record_type)->toBe('stamp_catalog_value');

        // Controleer het geregistreerde record-ID.
        expect($log->record_id)->toBe($this->catalogValue->id);

        // Controleer het geregistreerde actietype.
        expect($log->action)->toBe('updated');

        // Controleer of beide JSON-velden als arrays beschikbaar zijn.
        expect($log->old_values)->toBeArray();

        expect($log->new_values)->toBeArray();

        // Controleer of beide arrays precies één veld bevatten.
        expect($log->old_values)->toHaveCount(1);

        expect($log->new_values)->toHaveCount(1);

        // Controleer of uitsluitend needs_review is geregistreerd.
        expect(array_keys($log->old_values))
            ->toBe(['needs_review']);

        expect(array_keys($log->new_values))
            ->toBe(['needs_review']);

        // Controleer de oorspronkelijke controlemarkering.
        expect(in_array(
            $log->old_values['needs_review'],
            [true, 1],
            true
        ))->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(in_array(
            $log->new_values['needs_review'],
            [false, 0],
            true
        ))->toBeTrue();

        // Controleer expliciet of ongewijzigde en technische
        // velden niet in de geschiedenisregistratie voorkomen.
        $excludedFields = [
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'catalog_value',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ];

        foreach ($excludedFields as $field) {
            expect($log->old_values)->not->toHaveKey($field);

            expect($log->new_values)->not->toHaveKey($field);
        }

        // Haal alle bijgewerkte databaseattributen op.
        $reviewedAttributes = $reviewedCatalogValue->getAttributes();

        // Controleer of alle overige databaseattributen
        // volledig ongewijzigd zijn gebleven.
        $expectedUnchangedAttributes = $originalAttributes;

        $actualUnchangedAttributes = $reviewedAttributes;

        unset(
            $expectedUnchangedAttributes['needs_review'],
            $expectedUnchangedAttributes['updated_at'],
            $actualUnchangedAttributes['needs_review'],
            $actualUnchangedAttributes['updated_at']
        );

        expect($actualUnchangedAttributes)
            ->toBe($expectedUnchangedAttributes);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect($reviewedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer de oorspronkelijke valuta.
        expect($reviewedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer de oorspronkelijke notities.
        expect($reviewedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of created_at volledig ongewijzigd is gebleven.
        expect($reviewedAttributes['created_at'])
            ->toBe($originalAttributes['created_at']);

        // Controleer of de geschiedenisbatch precies één
        // gekoppelde geschiedenisregel bevat.
        expect($batch->logs()->count())->toBe(1);

        // Controleer of de geschiedenisregel daadwerkelijk
        // in de database is opgeslagen.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
            'action' => 'updated',
        ]);

        // Controleer of de cataloguswaarde correct
        // in de database is bijgewerkt.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => false,
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een al gecontroleerde cataloguswaarde veroorzaakt geen geschiedenisregistratie', function () {
    // Maak de cataloguswaarde vooraf al gecontroleerd.
    $this->catalogValue->update([
        'needs_review' => false,
    ]);

    // Haal de oorspronkelijke gecontroleerde cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of de cataloguswaarde al gecontroleerd is.
    expect($originalCatalogValue->needs_review)->toBeFalse();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer de al gecontroleerde cataloguswaarde
        // opnieuw als gecontroleerd te markeren.
        $response = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            );

        // Controleer of de aanvraag correct is afgehandeld.
        $response->assertRedirect($reviewUrl);

        $response->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $response->assertSessionHasNoErrors();

        // Controleer of de beheerder nog steeds is ingelogd.
        $this->assertAuthenticatedAs($this->admin);

        // Haal de cataloguswaarde opnieuw op.
        $reviewedCatalogValue = $this->catalogValue->fresh();

        // Controleer of de controlemarkering ongewijzigd is gebleven.
        expect($reviewedCatalogValue->needs_review)->toBeFalse();

        // Controleer of alle databaseattributen
        // volledig ongewijzigd zijn gebleven.
        expect($reviewedCatalogValue->getAttributes())
            ->toBe($originalValueAttributes);

        // Controleer expliciet of updated_at niet is gewijzigd.
        expect(
            $reviewedCatalogValue->updated_at->equalTo($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($reviewedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer of de oorspronkelijke valuta behouden is.
        expect($reviewedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer of de oorspronkelijke notities behouden zijn.
        expect($reviewedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of created_at ongewijzigd is gebleven.
        expect($reviewedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of er geen geschiedenisbatch is aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        // Controleer of er geen geschiedenisregel is aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Controleer of er geen geschiedenisbatch
        // voor deze cataloguseditie bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->edition->id,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor deze cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
        ]);

        // Controleer of er geen actieve geschiedenisbatch is achtergebleven.
        expect($history->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de oorspronkelijke cataloguswaarde
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => false,
            'created_at' => $originalValueAttributes['created_at'],
            'updated_at' => $originalValueAttributes['updated_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een herhaalde controlebewerking veroorzaakt geen dubbele geschiedenisregistratie', function () {
    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($this->catalogValue->fresh()->needs_review)
        ->toBeTrue();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bewaar de oorspronkelijke attributen van de gerelateerde records.
    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Bepaal de route voor de controlebewerking.
    $updateUrl = route(
        'backend.stamp-catalog-values.review',
        $this->catalogValue
    );

    // Verplaats de testtijd voor de eerste controlebewerking.
    $this->travel(1)->minutes();

    try {
        // Voer de eerste controlebewerking uit als beheerder.
        $firstResponse = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch($updateUrl);

        // Controleer of de eerste aanvraag succesvol is afgehandeld.
        $firstResponse->assertRedirect($reviewUrl);

        $firstResponse->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $firstResponse->assertSessionHasNoErrors();

        // Haal de gecontroleerde cataloguswaarde op.
        $firstReviewedValue = $this->catalogValue->fresh();

        // Controleer of de cataloguswaarde nu gecontroleerd is.
        expect($firstReviewedValue->needs_review)->toBeFalse();

        // Controleer of de eerste aanvraag precies één
        // geschiedenisbatch en één geschiedenisregel heeft aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de oorspronkelijke geschiedenisregistratie op.
        $firstBatch = CatalogChangeBatch::query()->sole();

        $firstLog = CatalogChangeLog::query()->sole();

        // Controleer de oorspronkelijke geschiedenisbatch.
        expect($firstBatch->operation)->toBe('review');

        expect($firstBatch->user_id)->toBe($this->admin->id);

        expect($firstBatch->catalog_edition_id)
            ->toBe($this->edition->id);

        expect($firstBatch->logs()->count())->toBe(1);

        // Controleer de oorspronkelijke geschiedenisregel.
        expect($firstLog->catalog_change_batch_id)
            ->toBe($firstBatch->id);

        expect($firstLog->record_type)
            ->toBe('stamp_catalog_value');

        expect($firstLog->record_id)
            ->toBe($this->catalogValue->id);

        expect($firstLog->action)->toBe('updated');

        // Controleer de oorspronkelijke wijzigingsgegevens.
        expect(array_keys($firstLog->old_values))
            ->toBe(['needs_review']);

        expect(array_keys($firstLog->new_values))
            ->toBe(['needs_review']);

        expect(in_array(
            $firstLog->old_values['needs_review'],
            [true, 1],
            true
        ))->toBeTrue();

        expect(in_array(
            $firstLog->new_values['needs_review'],
            [false, 0],
            true
        ))->toBeTrue();

        // Bewaar alle attributen na de eerste controlebewerking.
        $firstValueAttributes = $firstReviewedValue->getAttributes();

        $firstBatchAttributes = $firstBatch->getAttributes();

        $firstLogAttributes = $firstLog->getAttributes();

        // Bewaar de oorspronkelijke wijzigingsgegevens.
        $firstOldValues = $firstLog->old_values;

        $firstNewValues = $firstLog->new_values;

        // Bewaar het wijzigingstijdstip van de cataloguswaarde.
        $firstUpdatedAt = $firstReviewedValue->updated_at;

        // Controleer of de actieve geschiedenisbatch is opgeruimd.
        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Verplaats de testtijd opnieuw zodat eventuele ongewenste
        // wijzigingen van tijdstempels betrouwbaar zichtbaar worden.
        $this->travel(1)->minutes();

        // Voer dezelfde controlebewerking opnieuw uit.
        $secondResponse = $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch($updateUrl);

        // Controleer of ook de tweede aanvraag correct is afgehandeld.
        $secondResponse->assertRedirect($reviewUrl);

        $secondResponse->assertSessionHas(
            'success',
            'De cataloguswaarde is als gecontroleerd gemarkeerd.'
        );

        $secondResponse->assertSessionHasNoErrors();

        // Controleer of de beheerder nog steeds is ingelogd.
        $this->assertAuthenticatedAs($this->admin);

        // Haal de cataloguswaarde na de tweede aanvraag op.
        $secondReviewedValue = $this->catalogValue->fresh();

        // Controleer of de controlemarkering ongewijzigd is gebleven.
        expect($secondReviewedValue->needs_review)->toBeFalse();

        // Controleer of alle databaseattributen van de cataloguswaarde
        // sinds de eerste controlebewerking ongewijzigd zijn gebleven.
        expect($secondReviewedValue->getAttributes())
            ->toBe($firstValueAttributes);

        // Controleer expliciet of updated_at niet opnieuw is gewijzigd.
        expect(
            $secondReviewedValue->updated_at->equalTo($firstUpdatedAt)
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($secondReviewedValue->catalog_value)
            ->toBe('12.50');

        // Controleer of de oorspronkelijke valuta behouden is.
        expect($secondReviewedValue->currency)
            ->toBe('EUR');

        // Controleer of de oorspronkelijke notities behouden zijn.
        expect($secondReviewedValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of er geen tweede geschiedenisbatch is aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        // Controleer of er geen tweede geschiedenisregel is aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de geschiedenisregistratie na de tweede aanvraag op.
        $secondBatch = CatalogChangeBatch::query()->sole();

        $secondLog = CatalogChangeLog::query()->sole();

        // Controleer of de oorspronkelijke geschiedenisbatch behouden is.
        expect($secondBatch->id)->toBe($firstBatch->id);

        // Controleer of de oorspronkelijke geschiedenisregel behouden is.
        expect($secondLog->id)->toBe($firstLog->id);

        // Controleer of alle attributen van de oorspronkelijke
        // geschiedenisbatch volledig ongewijzigd zijn gebleven.
        expect($secondBatch->getAttributes())
            ->toBe($firstBatchAttributes);

        // Controleer of alle attributen van de oorspronkelijke
        // geschiedenisregel volledig ongewijzigd zijn gebleven.
        expect($secondLog->getAttributes())
            ->toBe($firstLogAttributes);

        // Controleer of de oorspronkelijke wijzigingsgegevens
        // volledig ongewijzigd zijn gebleven.
        expect($secondLog->old_values)->toBe($firstOldValues);

        expect($secondLog->new_values)->toBe($firstNewValues);

        // Controleer of de oorspronkelijke koppeling behouden is.
        expect($secondLog->catalog_change_batch_id)
            ->toBe($firstBatch->id);

        expect($secondBatch->logs()->count())->toBe(1);

        expect($secondBatch->logs()->sole()->id)
            ->toBe($firstLog->id);

        // Controleer of er geen actieve geschiedenisbatch is achtergebleven.
        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de gerelateerde records
        // volledig ongewijzigd zijn gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de oorspronkelijke geschiedenisbatch
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $firstBatch->id,
            'catalog_edition_id' => $this->edition->id,
            'user_id' => $this->admin->id,
            'operation' => 'review',
            'created_at' => $firstBatchAttributes['created_at'],
            'updated_at' => $firstBatchAttributes['updated_at'],
        ]);

        // Controleer of de oorspronkelijke geschiedenisregel
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $firstLog->id,
            'catalog_change_batch_id' => $firstBatch->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
            'action' => 'updated',
            'created_at' => $firstLogAttributes['created_at'],
            'updated_at' => $firstLogAttributes['updated_at'],
        ]);

        // Controleer of de cataloguswaarde correct
        // in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => false,
            'created_at' => $firstValueAttributes['created_at'],
            'updated_at' => $firstValueAttributes['updated_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een niet-ingelogde gebruiker veroorzaakt geen geschiedenisregistratie', function () {
    // Controleer of er geen gebruiker is ingelogd.
    $this->assertGuest();

    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer zonder ingelogde gebruiker
        // de cataloguswaarde als gecontroleerd te markeren.
        $response = $this->patch(
            route('backend.stamp-catalog-values.review', $this->catalogValue)
        );

        // Controleer of de gebruiker naar de inlogpagina wordt verwezen.
        $response->assertRedirect(route('login'));

        // Controleer of er geen succesmelding is teruggegeven.
        $response->assertSessionMissing('success');

        // Controleer of er nog steeds geen gebruiker is ingelogd.
        $this->assertGuest();

        // Haal de cataloguswaarde opnieuw op.
        $unchangedCatalogValue = $this->catalogValue->fresh();

        // Controleer of de cataloguswaarde
        // nog steeds gecontroleerd moet worden.
        expect($unchangedCatalogValue->needs_review)->toBeTrue();

        // Controleer of alle databaseattributen
        // volledig ongewijzigd zijn gebleven.
        expect($unchangedCatalogValue->getAttributes())
            ->toBe($originalValueAttributes);

        // Controleer expliciet of updated_at niet is gewijzigd.
        expect(
            $unchangedCatalogValue->updated_at->equalTo($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($unchangedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer of de oorspronkelijke valuta behouden is.
        expect($unchangedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer of de oorspronkelijke notities behouden zijn.
        expect($unchangedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of created_at ongewijzigd is gebleven.
        expect($unchangedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of er geen geschiedenisbatch is aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        // Controleer of er geen geschiedenisregel is aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Controleer of er geen geschiedenisbatch
        // voor deze cataloguseditie bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->edition->id,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor deze cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
        ]);

        // Controleer of er geen actieve geschiedenisbatch is achtergebleven.
        expect($history->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de oorspronkelijke cataloguswaarde
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => true,
            'created_at' => $originalValueAttributes['created_at'],
            'updated_at' => $originalValueAttributes['updated_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een gewone gebruiker veroorzaakt geen geschiedenisregistratie', function () {
    // Controleer of de gewone gebruiker geen beheerder is.
    expect($this->user->fresh()->is_admin)->toBeFalse();

    // Controleer of de beheerder wel beheerdersrechten heeft.
    expect($this->admin->fresh()->is_admin)->toBeTrue();

    // Controleer of het om twee verschillende gebruikers gaat.
    expect($this->user->id)->not->toBe($this->admin->id);

    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Log in als gewone gebruiker.
    $this->actingAs($this->user);

    // Controleer of de juiste gebruiker is ingelogd.
    $this->assertAuthenticatedAs($this->user);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer als gewone gebruiker zonder beheerdersrechten
        // de cataloguswaarde als gecontroleerd te markeren.
        $response = $this->patch(
            route('backend.stamp-catalog-values.review', $this->catalogValue)
        );

        // Controleer of de aanvraag wordt geweigerd.
        $response->assertForbidden();

        // Controleer of er geen succesmelding is teruggegeven.
        $response->assertSessionMissing('success');

        // Controleer of de gewone gebruiker nog steeds is ingelogd.
        $this->assertAuthenticatedAs($this->user);

        // Haal de cataloguswaarde opnieuw op.
        $unchangedCatalogValue = $this->catalogValue->fresh();

        // Controleer of de cataloguswaarde
        // nog steeds gecontroleerd moet worden.
        expect($unchangedCatalogValue->needs_review)->toBeTrue();

        // Controleer of alle databaseattributen
        // volledig ongewijzigd zijn gebleven.
        expect($unchangedCatalogValue->getAttributes())
            ->toBe($originalValueAttributes);

        // Controleer expliciet of updated_at niet is gewijzigd.
        expect(
            $unchangedCatalogValue->updated_at->equalTo($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($unchangedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer of de oorspronkelijke valuta behouden is.
        expect($unchangedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer of de oorspronkelijke notities behouden zijn.
        expect($unchangedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of created_at ongewijzigd is gebleven.
        expect($unchangedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of er geen geschiedenisbatch is aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        // Controleer of er geen geschiedenisregel is aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Controleer of er geen geschiedenisbatch
        // voor deze cataloguseditie bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->edition->id,
        ]);

        // Controleer of er geen geschiedenisbatch
        // voor de gewone gebruiker bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->user->id,
        ]);

        // Controleer of er geen geschiedenisbatch
        // ten onrechte aan de beheerder is gekoppeld.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->admin->id,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor deze cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
        ]);

        // Controleer of er geen actieve geschiedenisbatch is achtergebleven.
        expect($history->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de gewone gebruiker
        // volledig ongewijzigd is gebleven.
        expect($this->user->fresh()->getAttributes())
            ->toBe($originalUserAttributes);

        // Controleer of de beheerder volledig ongewijzigd is gebleven.
        expect($this->admin->fresh()->getAttributes())
            ->toBe($originalAdminAttributes);

        // Controleer of de gewone gebruiker
        // nog steeds geen beheerdersrechten heeft.
        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Controleer of de beheerder zijn beheerdersrechten behoudt.
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => true,
            'created_at' => $originalValueAttributes['created_at'],
            'updated_at' => $originalValueAttributes['updated_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een niet-bestaande cataloguswaarde veroorzaakt geen geschiedenisregistratie', function () {
    // Controleer of de beheerder over beheerdersrechten beschikt.
    expect($this->admin->fresh()->is_admin)->toBeTrue();

    // Bepaal het ID van een niet-bestaande cataloguswaarde.
    $nonExistingCatalogValueId = 999999;

    // Controleer of dit ID daadwerkelijk niet bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $nonExistingCatalogValueId,
    ]);

    // Haal de oorspronkelijke bestaande cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de bestaande cataloguswaarde
    // nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Controleer of het bestaande en niet-bestaande ID verschillen.
    expect($originalCatalogValue->id)
        ->not->toBe($nonExistingCatalogValueId);

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Log in als beheerder.
    $this->actingAs($this->admin);

    // Controleer of de juiste beheerder is ingelogd.
    $this->assertAuthenticatedAs($this->admin);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer een niet-bestaande cataloguswaarde
        // als gecontroleerd te markeren.
        $response = $this->patch(
            route(
                'backend.stamp-catalog-values.review',
                $nonExistingCatalogValueId
            )
        );

        // Controleer of Laravel een HTTP 404-respons teruggeeft.
        $response->assertNotFound();

        // Controleer of er geen succesmelding is teruggegeven.
        $response->assertSessionMissing('success');

        // Controleer of de beheerder nog steeds is ingelogd.
        $this->assertAuthenticatedAs($this->admin);

        // Controleer of de niet-bestaande cataloguswaarde
        // niet alsnog is aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $nonExistingCatalogValueId,
        ]);

        // Haal de oorspronkelijke bestaande cataloguswaarde opnieuw op.
        $unchangedCatalogValue = $this->catalogValue->fresh();

        // Controleer of de bestaande cataloguswaarde
        // nog steeds gecontroleerd moet worden.
        expect($unchangedCatalogValue->needs_review)->toBeTrue();

        // Controleer of alle databaseattributen
        // volledig ongewijzigd zijn gebleven.
        expect($unchangedCatalogValue->getAttributes())
            ->toBe($originalValueAttributes);

        // Controleer expliciet of updated_at niet is gewijzigd.
        expect(
            $unchangedCatalogValue->updated_at->equalTo($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of de oorspronkelijke cataloguswaarde behouden is.
        expect($unchangedCatalogValue->catalog_value)
            ->toBe('12.50');

        // Controleer of de oorspronkelijke valuta behouden is.
        expect($unchangedCatalogValue->currency)
            ->toBe('EUR');

        // Controleer of de oorspronkelijke notities behouden zijn.
        expect($unchangedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        // Controleer of created_at ongewijzigd is gebleven.
        expect($unchangedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of er geen geschiedenisbatch is aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        // Controleer of er geen geschiedenisregel is aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Controleer of er geen geschiedenisbatch
        // voor deze cataloguseditie bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->edition->id,
        ]);

        // Controleer of er geen geschiedenisbatch
        // voor de beheerder bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->admin->id,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor de niet-bestaande cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $nonExistingCatalogValueId,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor de bestaande cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
        ]);

        // Controleer of er geen actieve geschiedenisbatch is achtergebleven.
        expect($history->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de beheerder volledig ongewijzigd is gebleven.
        expect($this->admin->fresh()->getAttributes())
            ->toBe($originalAdminAttributes);

        // Controleer of de gewone gebruiker
        // volledig ongewijzigd is gebleven.
        expect($this->user->fresh()->getAttributes())
            ->toBe($originalUserAttributes);

        // Controleer of de beheerder zijn beheerdersrechten behoudt.
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        // Controleer of de gewone gebruiker
        // nog steeds geen beheerdersrechten heeft.
        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Controleer of de oorspronkelijke bestaande cataloguswaarde
        // nog steeds correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => true,
            'created_at' => $originalValueAttributes['created_at'],
            'updated_at' => $originalValueAttributes['updated_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een fout tijdens de modelwijziging veroorzaakt een volledige rollback', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Bewaar het ID van de cataloguswaarde waarop de fout
    // gesimuleerd moet worden.
    $targetCatalogValueId = $this->catalogValue->id;

    // Houd bij hoe vaak de gesimuleerde fout wordt bereikt.
    $listenerCallCount = 0;

    // Bewaar de databasetoestand vlak voordat de fout optreedt.
    $stateBeforeFailure = [];

    // Bewaar de oorspronkelijke Eloquent event dispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de event dispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Gebruik tijdelijk de gekopieerde event dispatcher.
        StampCatalogValue::setEventDispatcher($temporaryDispatcher);

        // Voeg een tijdelijke listener toe die tijdens
        // de modelwijziging een uitzondering veroorzaakt.
        StampCatalogValue::updating(function (
            StampCatalogValue $catalogValue
        ) use (
            $targetCatalogValueId,
            &$listenerCallCount,
            &$stateBeforeFailure
        ) {
            // Simuleer de fout uitsluitend voor de bedoelde cataloguswaarde.
            if ($catalogValue->id !== $targetCatalogValueId) {
                return;
            }

            // Registreer dat de tijdelijke listener is bereikt.
            $listenerCallCount++;

            // Haal de actieve geschiedenisservice op.
            $activeHistory = app(CatalogChangeHistoryService::class);

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = $activeHistory->getActiveBatch();

            // Haal de nog opgeslagen cataloguswaarde op.
            $storedCatalogValue = StampCatalogValue::query()
                ->findOrFail($targetCatalogValueId);

            // Bewaar de toestand vlak voordat de uitzondering optreedt.
            $stateBeforeFailure = [
                'batch_count' => CatalogChangeBatch::query()->count(),
                'log_count' => CatalogChangeLog::query()->count(),
                'active_batch_id' => $activeBatch?->id,
                'active_batch_exists' => $activeBatch !== null
                    && CatalogChangeBatch::query()
                        ->whereKey($activeBatch->id)
                        ->exists(),
                'active_batch_edition_id' => $activeBatch?->catalog_edition_id,
                'active_batch_user_id' => $activeBatch?->user_id,
                'active_batch_operation' => $activeBatch?->operation,
                'active_batch_log_count' => $activeBatch?->logs()->count(),
                'stored_needs_review' => $storedCatalogValue->needs_review,
                'pending_needs_review' => $catalogValue->needs_review,
                'stored_catalog_value' => $storedCatalogValue->catalog_value,
                'pending_catalog_value' => $catalogValue->catalog_value,
                'needs_review_is_dirty' => $catalogValue->isDirty('needs_review'),
                'catalog_value_is_dirty' => $catalogValue->isDirty('catalog_value'),
            ];

            // Simuleer een fout voordat de modelwijziging
            // naar de database wordt geschreven.
            throw new RuntimeException(
                'Gesimuleerde fout tijdens de controlebewerking van de cataloguswaarde.'
            );
        });

        // Schakel de normale exception handling tijdelijk uit,
        // zodat Pest de uitzondering rechtstreeks kan controleren.
        $this->withoutExceptionHandling();

        // Voer de controlebewerking uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            )
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens de controlebewerking van de cataloguswaarde.'
        );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent event dispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de normale exception handling.
        $this->withExceptionHandling();

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer of de oorspronkelijke event dispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer of de tijdelijke listener precies één keer is bereikt.
    expect($listenerCallCount)->toBe(1);

    // Controleer of de toestand vóór de fout daadwerkelijk is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer of er vóór de fout precies één geschiedenisbatch bestond.
    expect($stateBeforeFailure['batch_count'])->toBe(1);

    // Controleer of er vóór de fout nog geen geschiedenisregel bestond.
    expect($stateBeforeFailure['log_count'])->toBe(0);

    // Controleer of er een actieve geschiedenisbatch aanwezig was.
    expect($stateBeforeFailure['active_batch_id'])->not->toBeNull();

    // Controleer of de actieve geschiedenisbatch daadwerkelijk
    // in de database aanwezig was.
    expect($stateBeforeFailure['active_batch_exists'])->toBeTrue();

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste cataloguseditie was gekoppeld.
    expect($stateBeforeFailure['active_batch_edition_id'])
        ->toBe($this->edition->id);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste beheerder was gekoppeld.
    expect($stateBeforeFailure['active_batch_user_id'])
        ->toBe($this->admin->id);

    // Controleer of de actieve geschiedenisbatch
    // de juiste bewerking bevatte.
    expect($stateBeforeFailure['active_batch_operation'])
        ->toBe('review');

    // Controleer of de actieve geschiedenisbatch
    // nog geen geschiedenisregels bevatte.
    expect($stateBeforeFailure['active_batch_log_count'])
        ->toBe(0);

    // Controleer of de opgeslagen cataloguswaarde
    // vóór de fout nog gecontroleerd moest worden.
    expect($stateBeforeFailure['stored_needs_review'])
        ->toBeTrue();

    // Controleer of het model klaarstond om de controlemarkering
    // naar false te wijzigen.
    expect($stateBeforeFailure['pending_needs_review'])
        ->toBeFalse();

    // Controleer of de oorspronkelijke cataloguswaarde
    // zowel in de database als in het model behouden was.
    expect($stateBeforeFailure['stored_catalog_value'])
        ->toBe('12.50');

    expect($stateBeforeFailure['pending_catalog_value'])
        ->toBe('12.50');

    // Controleer of uitsluitend de controlemarkering
    // als functionele wijziging klaarstond.
    expect($stateBeforeFailure['needs_review_is_dirty'])
        ->toBeTrue();

    expect($stateBeforeFailure['catalog_value_is_dirty'])
        ->toBeFalse();

    // Haal de cataloguswaarde na de rollback opnieuw op.
    $restoredCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde
    // nog steeds gecontroleerd moet worden.
    expect($restoredCatalogValue->needs_review)->toBeTrue();

    // Controleer of alle oorspronkelijke databaseattributen
    // volledig behouden zijn gebleven.
    expect($restoredCatalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer expliciet of updated_at ongewijzigd is gebleven.
    expect(
        $restoredCatalogValue->updated_at->equalTo($originalUpdatedAt)
    )->toBeTrue();

    // Controleer of de oorspronkelijke cataloguswaarde behouden is.
    expect($restoredCatalogValue->catalog_value)
        ->toBe('12.50');

    // Controleer of de oorspronkelijke valuta behouden is.
    expect($restoredCatalogValue->currency)
        ->toBe('EUR');

    // Controleer of de oorspronkelijke notities behouden zijn.
    expect($restoredCatalogValue->notes)
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of created_at ongewijzigd is gebleven.
    expect($restoredCatalogValue->getAttributes()['created_at'])
        ->toBe($originalValueAttributes['created_at']);

    // Controleer of de aangemaakte geschiedenisbatch
    // volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer of er geen geschiedenisregels zijn achtergebleven.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer expliciet of de tijdelijke geschiedenisbatch
    // niet meer in de database bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $stateBeforeFailure['active_batch_id'],
    ]);

    // Controleer of er geen geschiedenisregel
    // voor de cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
        'record_id' => $this->catalogValue->id,
    ]);

    // Controleer of de actieve geschiedenisbatch is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer of de catalogusvermelding
    // volledig ongewijzigd is gebleven.
    expect($this->catalogEntry->fresh()->getAttributes())
        ->toBe($originalEntryAttributes);

    // Controleer of de postzegel volledig ongewijzigd is gebleven.
    expect($this->stamp->fresh()->getAttributes())
        ->toBe($originalStampAttributes);

    // Controleer of de waarderingscategorie
    // volledig ongewijzigd is gebleven.
    expect($this->condition->fresh()->getAttributes())
        ->toBe($originalConditionAttributes);

    // Controleer of de cataloguseditie
    // volledig ongewijzigd is gebleven.
    expect($this->edition->fresh()->getAttributes())
        ->toBe($originalEditionAttributes);

    // Controleer of de catalogus volledig ongewijzigd is gebleven.
    expect($this->catalog->fresh()->getAttributes())
        ->toBe($originalCatalogAttributes);

    // Controleer of de beheerder volledig ongewijzigd is gebleven.
    expect($this->admin->fresh()->getAttributes())
        ->toBe($originalAdminAttributes);

    // Controleer of de gewone gebruiker
    // volledig ongewijzigd is gebleven.
    expect($this->user->fresh()->getAttributes())
        ->toBe($originalUserAttributes);

    // Controleer of de oorspronkelijke cataloguswaarde
    // nog steeds correct in de database aanwezig is.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->catalogValue->id,
        'stamp_catalog_entry_id' => $this->catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
        'needs_review' => true,
        'created_at' => $originalValueAttributes['created_at'],
        'updated_at' => $originalValueAttributes['updated_at'],
    ]);

    // Controleer of er geen ongewenste records
    // zijn toegevoegd of verwijderd.
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('countries', 1);
    $this->assertDatabaseCount('catalogs', 1);
    $this->assertDatabaseCount('catalog_editions', 1);
    $this->assertDatabaseCount('stamps', 1);
    $this->assertDatabaseCount('conditions', 1);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('series_catalog_values', 0);
});

test('een fout tijdens de geschiedenisregistratie veroorzaakt een volledige rollback', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Bewaar het ID van de cataloguswaarde waarop de fout
    // gesimuleerd moet worden.
    $targetCatalogValueId = $this->catalogValue->id;

    // Houd bij hoe vaak de gesimuleerde fout wordt bereikt.
    $listenerCallCount = 0;

    // Bewaar de databasetoestand vlak voordat de fout optreedt.
    $stateBeforeFailure = [];

    // Bewaar de oorspronkelijke Eloquent event dispatcher.
    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    // Maak een tijdelijke kopie van de event dispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om de tijdelijke wijziging
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Gebruik tijdelijk de gekopieerde event dispatcher.
        CatalogChangeLog::setEventDispatcher($temporaryDispatcher);

        // Voeg een tijdelijke listener toe die een uitzondering
        // veroorzaakt nadat de geschiedenisregel is opgeslagen.
        CatalogChangeLog::created(function (
            CatalogChangeLog $changeLog
        ) use (
            $targetCatalogValueId,
            &$listenerCallCount,
            &$stateBeforeFailure
        ) {
            // Simuleer de fout uitsluitend voor de bedoelde
            // cataloguswaarde en geschiedenisactie.
            if (
                $changeLog->record_type !== 'stamp_catalog_value'
                || $changeLog->record_id !== $targetCatalogValueId
                || $changeLog->action !== 'updated'
            ) {
                return;
            }

            // Registreer dat de tijdelijke listener is bereikt.
            $listenerCallCount++;

            // Haal de actieve geschiedenisservice op.
            $activeHistory = app(CatalogChangeHistoryService::class);

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = $activeHistory->getActiveBatch();

            // Haal de inmiddels gewijzigde cataloguswaarde
            // rechtstreeks uit de database op.
            $storedCatalogValue = StampCatalogValue::query()
                ->findOrFail($targetCatalogValueId);

            // Haal de inmiddels opgeslagen geschiedenisregel
            // rechtstreeks uit de database op.
            $storedChangeLog = CatalogChangeLog::query()
                ->findOrFail($changeLog->id);

            // Bewaar de toestand vlak voordat de uitzondering optreedt.
            $stateBeforeFailure = [
                'batch_count' => CatalogChangeBatch::query()->count(),
                'log_count' => CatalogChangeLog::query()->count(),

                'active_batch_id' => $activeBatch?->id,
                'active_batch_exists' => $activeBatch !== null
                    && CatalogChangeBatch::query()
                        ->whereKey($activeBatch->id)
                        ->exists(),
                'active_batch_edition_id' => $activeBatch?->catalog_edition_id,
                'active_batch_user_id' => $activeBatch?->user_id,
                'active_batch_operation' => $activeBatch?->operation,
                'active_batch_log_count' => $activeBatch?->logs()->count(),

                'stored_needs_review' => $storedCatalogValue->needs_review,
                'stored_catalog_value' => $storedCatalogValue->catalog_value,
                'stored_currency' => $storedCatalogValue->currency,
                'stored_notes' => $storedCatalogValue->notes,
                'stored_updated_at' => $storedCatalogValue->updated_at,

                'log_id' => $storedChangeLog->id,
                'log_exists' => $storedChangeLog->exists,
                'log_batch_id' => $storedChangeLog->catalog_change_batch_id,
                'log_edition_id' => $storedChangeLog->catalog_edition_id,
                'log_stamp_id' => $storedChangeLog->stamp_id,
                'log_stamp_series_id' => $storedChangeLog->stamp_series_id,
                'log_condition_id' => $storedChangeLog->condition_id,
                'log_record_type' => $storedChangeLog->record_type,
                'log_record_id' => $storedChangeLog->record_id,
                'log_action' => $storedChangeLog->action,
                'log_old_values' => $storedChangeLog->old_values,
                'log_new_values' => $storedChangeLog->new_values,
            ];

            // Simuleer een fout nadat de cataloguswijziging
            // en geschiedenisregel naar de database zijn geschreven.
            throw new RuntimeException(
                'Gesimuleerde fout tijdens de geschiedenisregistratie van de controlebewerking.'
            );
        });

        // Schakel de normale exception handling tijdelijk uit,
        // zodat Pest de uitzondering rechtstreeks kan controleren.
        $this->withoutExceptionHandling();

        // Voer de controlebewerking uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            )
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens de geschiedenisregistratie van de controlebewerking.'
        );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent event dispatcher.
        CatalogChangeLog::setEventDispatcher($originalDispatcher);

        // Herstel altijd de normale exception handling.
        $this->withExceptionHandling();

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer of de oorspronkelijke event dispatcher is hersteld.
    expect(CatalogChangeLog::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer of de tijdelijke listener precies één keer is bereikt.
    expect($listenerCallCount)->toBe(1);

    // Controleer of de toestand vóór de fout daadwerkelijk is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer of er vóór de fout precies één geschiedenisbatch bestond.
    expect($stateBeforeFailure['batch_count'])->toBe(1);

    // Controleer of er vóór de fout precies één geschiedenisregel bestond.
    expect($stateBeforeFailure['log_count'])->toBe(1);

    // Controleer of er een actieve geschiedenisbatch aanwezig was.
    expect($stateBeforeFailure['active_batch_id'])->not->toBeNull();

    // Controleer of de actieve geschiedenisbatch daadwerkelijk
    // in de database aanwezig was.
    expect($stateBeforeFailure['active_batch_exists'])->toBeTrue();

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste cataloguseditie was gekoppeld.
    expect($stateBeforeFailure['active_batch_edition_id'])
        ->toBe($this->edition->id);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste beheerder was gekoppeld.
    expect($stateBeforeFailure['active_batch_user_id'])
        ->toBe($this->admin->id);

    // Controleer of de actieve geschiedenisbatch
    // de juiste bewerking bevatte.
    expect($stateBeforeFailure['active_batch_operation'])
        ->toBe('review');

    // Controleer of de actieve geschiedenisbatch
    // precies één geschiedenisregel bevatte.
    expect($stateBeforeFailure['active_batch_log_count'])
        ->toBe(1);

    // Controleer of de controlemarkering daadwerkelijk
    // naar false was gewijzigd in de database.
    expect($stateBeforeFailure['stored_needs_review'])
        ->toBeFalse();

    // Controleer of de oorspronkelijke cataloguswaarde behouden was.
    expect($stateBeforeFailure['stored_catalog_value'])
        ->toBe('12.50');

    // Controleer of de oorspronkelijke valuta behouden was.
    expect($stateBeforeFailure['stored_currency'])
        ->toBe('EUR');

    // Controleer of de oorspronkelijke notities behouden waren.
    expect($stateBeforeFailure['stored_notes'])
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of updated_at daadwerkelijk was gewijzigd.
    expect(
        $stateBeforeFailure['stored_updated_at']
            ->greaterThan($originalUpdatedAt)
    )->toBeTrue();

    // Controleer of de geschiedenisregel daadwerkelijk
    // in de database aanwezig was.
    expect($stateBeforeFailure['log_id'])->not->toBeNull();

    expect($stateBeforeFailure['log_exists'])->toBeTrue();

    // Controleer of de geschiedenisregel aan de actieve
    // geschiedenisbatch was gekoppeld.
    expect($stateBeforeFailure['log_batch_id'])
        ->toBe($stateBeforeFailure['active_batch_id']);

    // Controleer of de geschiedenisregel
    // aan de juiste cataloguseditie was gekoppeld.
    expect($stateBeforeFailure['log_edition_id'])
        ->toBe($this->edition->id);

    // Controleer of de geschiedenisregel
    // aan de juiste postzegel was gekoppeld.
    expect($stateBeforeFailure['log_stamp_id'])
        ->toBe($this->stamp->id);

    // Controleer of er geen onjuiste koppeling
    // met een postzegelserie bestond.
    expect($stateBeforeFailure['log_stamp_series_id'])
        ->toBeNull();

    // Controleer of de geschiedenisregel
    // aan de juiste waarderingscategorie was gekoppeld.
    expect($stateBeforeFailure['log_condition_id'])
        ->toBe($this->condition->id);

    // Controleer of het juiste recordtype was geregistreerd.
    expect($stateBeforeFailure['log_record_type'])
        ->toBe('stamp_catalog_value');

    // Controleer of het juiste record-ID was geregistreerd.
    expect($stateBeforeFailure['log_record_id'])
        ->toBe($this->catalogValue->id);

    // Controleer of de juiste geschiedenisactie was geregistreerd.
    expect($stateBeforeFailure['log_action'])
        ->toBe('updated');

    // Controleer of de oude en nieuwe waarden arrays waren.
    expect($stateBeforeFailure['log_old_values'])->toBeArray();

    expect($stateBeforeFailure['log_new_values'])->toBeArray();

    // Controleer of uitsluitend needs_review was geregistreerd.
    expect(array_keys($stateBeforeFailure['log_old_values']))
        ->toBe(['needs_review']);

    expect(array_keys($stateBeforeFailure['log_new_values']))
        ->toBe(['needs_review']);

    // Controleer of de oorspronkelijke controlemarkering
    // correct in old_values was opgeslagen.
    expect(in_array(
        $stateBeforeFailure['log_old_values']['needs_review'],
        [true, 1],
        true
    ))->toBeTrue();

    // Controleer of de gewijzigde controlemarkering
    // correct in new_values was opgeslagen.
    expect(in_array(
        $stateBeforeFailure['log_new_values']['needs_review'],
        [false, 0],
        true
    ))->toBeTrue();

    // Haal de cataloguswaarde na de rollback opnieuw op.
    $restoredCatalogValue = $this->catalogValue->fresh();

    // Controleer of de oorspronkelijke controlemarkering
    // daadwerkelijk is hersteld.
    expect($restoredCatalogValue->needs_review)->toBeTrue();

    // Controleer of alle oorspronkelijke databaseattributen
    // volledig zijn hersteld.
    expect($restoredCatalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer expliciet of updated_at is teruggedraaid.
    expect(
        $restoredCatalogValue->updated_at->equalTo($originalUpdatedAt)
    )->toBeTrue();

    // Controleer of het herstelde wijzigingstijdstip verschilt
    // van het tijdelijke wijzigingstijdstip vóór de fout.
    expect(
        $restoredCatalogValue->updated_at
            ->lessThan($stateBeforeFailure['stored_updated_at'])
    )->toBeTrue();

    // Controleer of de oorspronkelijke cataloguswaarde behouden is.
    expect($restoredCatalogValue->catalog_value)
        ->toBe('12.50');

    // Controleer of de oorspronkelijke valuta behouden is.
    expect($restoredCatalogValue->currency)
        ->toBe('EUR');

    // Controleer of de oorspronkelijke notities behouden zijn.
    expect($restoredCatalogValue->notes)
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of created_at ongewijzigd is gebleven.
    expect($restoredCatalogValue->getAttributes()['created_at'])
        ->toBe($originalValueAttributes['created_at']);

    // Controleer of de aangemaakte geschiedenisbatch
    // volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer of de aangemaakte geschiedenisregel
    // volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer expliciet of de tijdelijke geschiedenisbatch
    // niet meer in de database bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $stateBeforeFailure['active_batch_id'],
    ]);

    // Controleer expliciet of de tijdelijke geschiedenisregel
    // niet meer in de database bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'id' => $stateBeforeFailure['log_id'],
    ]);

    // Controleer of er geen geschiedenisregels
    // voor de tijdelijke geschiedenisbatch zijn achtergebleven.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_change_batch_id' => $stateBeforeFailure['active_batch_id'],
    ]);

    // Controleer of er geen geschiedenisregel
    // voor de cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
        'record_id' => $this->catalogValue->id,
    ]);

    // Controleer of de actieve geschiedenisbatch is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer of de catalogusvermelding
    // volledig ongewijzigd is gebleven.
    expect($this->catalogEntry->fresh()->getAttributes())
        ->toBe($originalEntryAttributes);

    // Controleer of de postzegel volledig ongewijzigd is gebleven.
    expect($this->stamp->fresh()->getAttributes())
        ->toBe($originalStampAttributes);

    // Controleer of de waarderingscategorie
    // volledig ongewijzigd is gebleven.
    expect($this->condition->fresh()->getAttributes())
        ->toBe($originalConditionAttributes);

    // Controleer of de cataloguseditie
    // volledig ongewijzigd is gebleven.
    expect($this->edition->fresh()->getAttributes())
        ->toBe($originalEditionAttributes);

    // Controleer of de catalogus volledig ongewijzigd is gebleven.
    expect($this->catalog->fresh()->getAttributes())
        ->toBe($originalCatalogAttributes);

    // Controleer of de beheerder volledig ongewijzigd is gebleven.
    expect($this->admin->fresh()->getAttributes())
        ->toBe($originalAdminAttributes);

    // Controleer of de gewone gebruiker
    // volledig ongewijzigd is gebleven.
    expect($this->user->fresh()->getAttributes())
        ->toBe($originalUserAttributes);

    // Controleer of de oorspronkelijke cataloguswaarde
    // weer volledig correct in de database aanwezig is.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->catalogValue->id,
        'stamp_catalog_entry_id' => $this->catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
        'needs_review' => true,
        'created_at' => $originalValueAttributes['created_at'],
        'updated_at' => $originalValueAttributes['updated_at'],
    ]);

    // Controleer of er geen ongewenste records
    // zijn toegevoegd of verwijderd.
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('countries', 1);
    $this->assertDatabaseCount('catalogs', 1);
    $this->assertDatabaseCount('catalog_editions', 1);
    $this->assertDatabaseCount('stamps', 1);
    $this->assertDatabaseCount('conditions', 1);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('series_catalog_values', 0);
});

test('de actieve geschiedenisbatch wordt opgeruimd na een succesvolle controlebewerking', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Bewaar het ID van de cataloguswaarde die gecontroleerd wordt.
    $targetCatalogValueId = $this->catalogValue->id;

    // Bewaar de geschiedenisservice die tijdens de bewerking actief is.
    /** @var CatalogChangeHistoryService|null $historyDuringOperation */
    $historyDuringOperation = null;

    // Bewaar de geschiedenisbatch die tijdens de bewerking actief is.
    /** @var CatalogChangeBatch|null $activeBatchDuringOperation */
    $activeBatchDuringOperation = null;

    // Houd bij hoe vaak de tijdelijke listener wordt bereikt.
    $listenerCallCount = 0;

    // Bewaar de toestand tijdens de controlebewerking.
    $stateDuringOperation = [];

    // Bewaar de oorspronkelijke Eloquent event dispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de event dispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om wijzigingen van updated_at
    // betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Gebruik tijdelijk de gekopieerde event dispatcher.
        StampCatalogValue::setEventDispatcher($temporaryDispatcher);

        // Registreer een tijdelijke listener die de actieve
        // geschiedenisbatch tijdens de controlebewerking vastlegt.
        StampCatalogValue::updating(function (
            StampCatalogValue $catalogValue
        ) use (
            $targetCatalogValueId,
            &$historyDuringOperation,
            &$activeBatchDuringOperation,
            &$listenerCallCount,
            &$stateDuringOperation
        ) {
            // Controleer uitsluitend de bedoelde cataloguswaarde.
            if ($catalogValue->id !== $targetCatalogValueId) {
                return;
            }

            // Registreer dat de tijdelijke listener is bereikt.
            $listenerCallCount++;

            // Bewaar de geschiedenisservice tijdens de bewerking.
            $historyDuringOperation = app(
                CatalogChangeHistoryService::class
            );

            // Bewaar de actieve geschiedenisbatch.
            $activeBatchDuringOperation = $historyDuringOperation
                ->getActiveBatch();

            // Haal de nog opgeslagen cataloguswaarde op.
            $storedCatalogValue = StampCatalogValue::query()
                ->findOrFail($targetCatalogValueId);

            // Bewaar de toestand tijdens de controlebewerking.
            $stateDuringOperation = [
                'batch_count' => CatalogChangeBatch::query()->count(),
                'log_count' => CatalogChangeLog::query()->count(),
                'active_batch_id' => $activeBatchDuringOperation?->id,
                'active_batch_exists' => $activeBatchDuringOperation !== null
                    && CatalogChangeBatch::query()
                        ->whereKey($activeBatchDuringOperation->id)
                        ->exists(),
                'active_batch_log_count' => $activeBatchDuringOperation
                    ?->logs()
                    ->count(),
                'stored_needs_review' => $storedCatalogValue->needs_review,
                'pending_needs_review' => $catalogValue->needs_review,
            ];
        });

        // Voer de controlebewerking uit als beheerder.
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

        // Controleer of de juiste beheerder is ingelogd.
        $this->assertAuthenticatedAs($this->admin);
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent event dispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer of de oorspronkelijke event dispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer of de tijdelijke listener precies één keer is bereikt.
    expect($listenerCallCount)->toBe(1);

    // Controleer of tijdens de bewerking dezelfde
    // geschiedenisservice is gebruikt.
    expect($historyDuringOperation)->toBe($history);

    // Controleer of er tijdens de bewerking
    // daadwerkelijk een actieve geschiedenisbatch bestond.
    expect($activeBatchDuringOperation)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste cataloguseditie was gekoppeld.
    expect($activeBatchDuringOperation->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste beheerder was gekoppeld.
    expect($activeBatchDuringOperation->user_id)
        ->toBe($this->admin->id);

    // Controleer of de juiste bewerking was geregistreerd.
    expect($activeBatchDuringOperation->operation)
        ->toBe('review');

    // Controleer de vastgelegde toestand tijdens de bewerking.
    expect($stateDuringOperation['batch_count'])->toBe(1);

    expect($stateDuringOperation['log_count'])->toBe(0);

    expect($stateDuringOperation['active_batch_id'])
        ->toBe($activeBatchDuringOperation->id);

    expect($stateDuringOperation['active_batch_exists'])
        ->toBeTrue();

    expect($stateDuringOperation['active_batch_log_count'])
        ->toBe(0);

    expect($stateDuringOperation['stored_needs_review'])
        ->toBeTrue();

    expect($stateDuringOperation['pending_needs_review'])
        ->toBeFalse();

    // Haal de gecontroleerde cataloguswaarde opnieuw op.
    $reviewedCatalogValue = $this->catalogValue->fresh();

    // Controleer of de controlebewerking succesvol is opgeslagen.
    expect($reviewedCatalogValue->needs_review)->toBeFalse();

    expect($reviewedCatalogValue->catalog_value)->toBe('12.50');

    expect($reviewedCatalogValue->currency)->toBe('EUR');

    expect($reviewedCatalogValue->notes)
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of precies één geschiedenisbatch
    // en één geschiedenisregel zijn opgeslagen.
    $this->assertDatabaseCount('catalog_change_batches', 1);
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de opgeslagen geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();

    $log = CatalogChangeLog::query()->sole();

    // Controleer of de opgeslagen geschiedenisbatch overeenkomt
    // met de geschiedenisbatch die tijdens de bewerking actief was.
    expect($batch->id)->toBe($activeBatchDuringOperation->id);

    expect($batch->operation)->toBe('review');

    expect($batch->user_id)->toBe($this->admin->id);

    expect($batch->catalog_edition_id)->toBe($this->edition->id);

    expect($batch->logs()->count())->toBe(1);

    // Controleer of de geschiedenisregel correct is gekoppeld.
    expect($log->catalog_change_batch_id)->toBe($batch->id);

    expect($log->record_type)->toBe('stamp_catalog_value');

    expect($log->record_id)->toBe($this->catalogValue->id);

    expect($log->action)->toBe('updated');

    // Controleer of uitsluitend needs_review is geregistreerd.
    expect(array_keys($log->old_values))->toBe(['needs_review']);

    expect(array_keys($log->new_values))->toBe(['needs_review']);

    expect(in_array(
        $log->old_values['needs_review'],
        [true, 1],
        true
    ))->toBeTrue();

    expect(in_array(
        $log->new_values['needs_review'],
        [false, 0],
        true
    ))->toBeTrue();

    // Controleer of de actieve geschiedenisbatch
    // na de succesvolle bewerking is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    expect($historyDuringOperation->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Bewaar alle attributen van de opgeslagen geschiedenisbatch.
    $originalBatchAttributes = $batch->getAttributes();

    // Bewaar alle attributen van de opgeslagen geschiedenisregel.
    $originalLogAttributes = $log->getAttributes();

    // Bewaar de oorspronkelijke oude en nieuwe geschiedeniswaarden.
    $originalLogOldValues = $log->old_values;

    $originalLogNewValues = $log->new_values;

    // Bewaar het wijzigingstijdstip na de controlebewerking.
    $reviewedUpdatedAt = $reviewedCatalogValue->updated_at;

    // Verplaats de testtijd tot na de succesvolle controlebewerking.
    $this->travelTo($reviewedUpdatedAt->copy()->addMinute());

    try {
        // Voer bewust een directe modelwijziging uit
        // zonder een nieuwe geschiedenisbatch te activeren.
        $reviewedCatalogValue->update([
            'catalog_value' => 17.50,
        ]);

        // Haal de gewijzigde cataloguswaarde opnieuw op.
        $changedCatalogValue = $reviewedCatalogValue->fresh();

        // Controleer of de latere modelwijziging is opgeslagen.
        expect($changedCatalogValue->catalog_value)->toBe('17.50');

        // Controleer of de gewijzigde cataloguswaarde
        // opnieuw gecontroleerd moet worden.
        expect($changedCatalogValue->needs_review)->toBeTrue();

        // Controleer of updated_at opnieuw is gewijzigd.
        expect(
            $changedCatalogValue->updated_at
                ->greaterThan($reviewedUpdatedAt)
        )->toBeTrue();

        // Controleer of de overige catalogusgegevens behouden zijn.
        expect($changedCatalogValue->currency)->toBe('EUR');

        expect($changedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        expect($changedCatalogValue->stamp_catalog_entry_id)
            ->toBe($this->catalogEntry->id);

        expect($changedCatalogValue->condition_id)
            ->toBe($this->condition->id);

        expect($changedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of de latere modelwijziging
        // geen extra geschiedenisbatch heeft veroorzaakt.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        // Controleer of de latere modelwijziging
        // geen extra geschiedenisregel heeft veroorzaakt.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        // Haal de oorspronkelijke geschiedenisbatch opnieuw op.
        $unchangedBatch = CatalogChangeBatch::query()->sole();

        // Haal de oorspronkelijke geschiedenisregel opnieuw op.
        $unchangedLog = CatalogChangeLog::query()->sole();

        // Controleer of de oorspronkelijke geschiedenisbatch
        // volledig ongewijzigd is gebleven.
        expect($unchangedBatch->getAttributes())
            ->toBe($originalBatchAttributes);

        // Controleer of de oorspronkelijke geschiedenisregel
        // volledig ongewijzigd is gebleven.
        expect($unchangedLog->getAttributes())
            ->toBe($originalLogAttributes);

        // Controleer of de oorspronkelijke geschiedeniswaarden
        // volledig ongewijzigd zijn gebleven.
        expect($unchangedLog->old_values)
            ->toBe($originalLogOldValues);

        expect($unchangedLog->new_values)
            ->toBe($originalLogNewValues);

        // Controleer of de geschiedenisbatch
        // nog steeds precies één geschiedenisregel bevat.
        expect($unchangedBatch->logs()->count())->toBe(1);

        // Controleer of de geschiedenisregel
        // nog steeds aan dezelfde geschiedenisbatch is gekoppeld.
        expect($unchangedLog->catalog_change_batch_id)
            ->toBe($batch->id);

        // Controleer of de geschiedenisbatch niet opnieuw actief is geworden.
        expect($history->getActiveBatch())->toBeNull();

        expect($historyDuringOperation->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de tijdelijke listener
        // niet opnieuw is uitgevoerd.
        expect($listenerCallCount)->toBe(1);

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de latere cataloguswijziging
        // correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 17.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => true,
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt opgeruimd na een mislukte controlebewerking', function () {
    // Haal de oorspronkelijke cataloguswaarde op.
    $originalCatalogValue = $this->catalogValue->fresh();

    // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
    expect($originalCatalogValue->needs_review)->toBeTrue();

    // Bewaar alle oorspronkelijke databaseattributen.
    $originalValueAttributes = $originalCatalogValue->getAttributes();

    $originalEntryAttributes = $this->catalogEntry
        ->fresh()
        ->getAttributes();

    $originalStampAttributes = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalConditionAttributes = $this->condition
        ->fresh()
        ->getAttributes();

    $originalEditionAttributes = $this->edition
        ->fresh()
        ->getAttributes();

    $originalCatalogAttributes = $this->catalog
        ->fresh()
        ->getAttributes();

    $originalAdminAttributes = $this->admin
        ->fresh()
        ->getAttributes();

    $originalUserAttributes = $this->user
        ->fresh()
        ->getAttributes();

    // Bewaar het oorspronkelijke wijzigingstijdstip.
    $originalUpdatedAt = $originalCatalogValue->updated_at;

    // Controleer of er vooraf geen geschiedenisregistratie bestaat.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer of er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Bewaar het ID van de cataloguswaarde waarop de fout
    // gesimuleerd moet worden.
    $targetCatalogValueId = $this->catalogValue->id;

    // Bewaar de geschiedenisservice die tijdens de bewerking actief is.
    /** @var CatalogChangeHistoryService|null $historyDuringOperation */
    $historyDuringOperation = null;

    // Bewaar de geschiedenisbatch die tijdens de bewerking actief is.
    /** @var CatalogChangeBatch|null $activeBatchDuringOperation */
    $activeBatchDuringOperation = null;

    // Houd bij hoe vaak de tijdelijke foutlistener wordt bereikt.
    $listenerCallCount = 0;

    // Bewaar de databasetoestand vlak voordat de fout optreedt.
    $stateBeforeFailure = [];

    // Bewaar de oorspronkelijke Eloquent event dispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de event dispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Bepaal de oorspronkelijke controlepagina.
    $reviewUrl = route('backend.catalogs.editions.review', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om eventuele ongewenste wijzigingen
    // van updated_at betrouwbaar te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Gebruik tijdelijk de gekopieerde event dispatcher.
        StampCatalogValue::setEventDispatcher($temporaryDispatcher);

        // Registreer een tijdelijke listener die de actieve
        // geschiedenisbatch vastlegt en vervolgens een fout veroorzaakt.
        StampCatalogValue::updating(function (
            StampCatalogValue $catalogValue
        ) use (
            $targetCatalogValueId,
            &$historyDuringOperation,
            &$activeBatchDuringOperation,
            &$listenerCallCount,
            &$stateBeforeFailure
        ) {
            // Simuleer de fout uitsluitend voor de bedoelde cataloguswaarde.
            if ($catalogValue->id !== $targetCatalogValueId) {
                return;
            }

            // Registreer dat de tijdelijke foutlistener is bereikt.
            $listenerCallCount++;

            // Bewaar de geschiedenisservice tijdens de bewerking.
            $historyDuringOperation = app(
                CatalogChangeHistoryService::class
            );

            // Bewaar de actieve geschiedenisbatch.
            $activeBatchDuringOperation = $historyDuringOperation
                ->getActiveBatch();

            // Haal de nog opgeslagen cataloguswaarde op.
            $storedCatalogValue = StampCatalogValue::query()
                ->findOrFail($targetCatalogValueId);

            // Bewaar de toestand vlak voordat de uitzondering optreedt.
            $stateBeforeFailure = [
                'batch_count' => CatalogChangeBatch::query()->count(),
                'log_count' => CatalogChangeLog::query()->count(),
                'active_batch_id' => $activeBatchDuringOperation?->id,
                'active_batch_exists' => $activeBatchDuringOperation !== null
                    && CatalogChangeBatch::query()
                        ->whereKey($activeBatchDuringOperation->id)
                        ->exists(),
                'active_batch_log_count' => $activeBatchDuringOperation
                    ?->logs()
                    ->count(),
                'stored_needs_review' => $storedCatalogValue->needs_review,
                'pending_needs_review' => $catalogValue->needs_review,
                'stored_catalog_value' => $storedCatalogValue->catalog_value,
                'pending_catalog_value' => $catalogValue->catalog_value,
            ];

            // Simuleer een fout nadat de geschiedenisbatch
            // is aangemaakt en geactiveerd.
            throw new RuntimeException(
                'Gesimuleerde fout voor het opruimen van de actieve geschiedenisbatch na een controlebewerking.'
            );
        });

        // Schakel de normale exception handling tijdelijk uit.
        $this->withoutExceptionHandling();

        // Voer de controlebewerking uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->actingAs($this->admin)
            ->from($reviewUrl)
            ->patch(
                route('backend.stamp-catalog-values.review', $this->catalogValue)
            )
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout voor het opruimen van de actieve geschiedenisbatch na een controlebewerking.'
        );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent event dispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de normale exception handling.
        $this->withExceptionHandling();

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer of de oorspronkelijke event dispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Controleer of de tijdelijke foutlistener precies één keer is bereikt.
    expect($listenerCallCount)->toBe(1);

    // Controleer of de toestand vóór de fout daadwerkelijk is vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer of tijdens de bewerking dezelfde
    // geschiedenisservice is gebruikt.
    expect($historyDuringOperation)->toBe($history);

    // Controleer of er tijdens de bewerking
    // daadwerkelijk een actieve geschiedenisbatch bestond.
    expect($activeBatchDuringOperation)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste cataloguseditie was gekoppeld.
    expect($activeBatchDuringOperation->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer of de actieve geschiedenisbatch
    // aan de juiste beheerder was gekoppeld.
    expect($activeBatchDuringOperation->user_id)
        ->toBe($this->admin->id);

    // Controleer of de juiste bewerking was geregistreerd.
    expect($activeBatchDuringOperation->operation)
        ->toBe('review');

    // Controleer of de juiste beschrijving was geregistreerd.
    expect($activeBatchDuringOperation->description)
        ->toBe('Cataloguswaarde als gecontroleerd gemarkeerd');

    // Controleer de vastgelegde toestand vóór de fout.
    expect($stateBeforeFailure['batch_count'])->toBe(1);

    expect($stateBeforeFailure['log_count'])->toBe(0);

    expect($stateBeforeFailure['active_batch_id'])
        ->toBe($activeBatchDuringOperation->id);

    expect($stateBeforeFailure['active_batch_exists'])
        ->toBeTrue();

    expect($stateBeforeFailure['active_batch_log_count'])
        ->toBe(0);

    expect($stateBeforeFailure['stored_needs_review'])
        ->toBeTrue();

    expect($stateBeforeFailure['pending_needs_review'])
        ->toBeFalse();

    expect($stateBeforeFailure['stored_catalog_value'])
        ->toBe('12.50');

    expect($stateBeforeFailure['pending_catalog_value'])
        ->toBe('12.50');

    // Haal de cataloguswaarde na de rollback opnieuw op.
    $restoredCatalogValue = $this->catalogValue->fresh();

    // Controleer of de oorspronkelijke controlemarkering behouden is.
    expect($restoredCatalogValue->needs_review)->toBeTrue();

    // Controleer of alle oorspronkelijke databaseattributen
    // volledig behouden zijn gebleven.
    expect($restoredCatalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer of updated_at ongewijzigd is gebleven.
    expect(
        $restoredCatalogValue->updated_at->equalTo($originalUpdatedAt)
    )->toBeTrue();

    // Controleer of de oorspronkelijke cataloguswaarde behouden is.
    expect($restoredCatalogValue->catalog_value)
        ->toBe('12.50');

    expect($restoredCatalogValue->currency)
        ->toBe('EUR');

    expect($restoredCatalogValue->notes)
        ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

    // Controleer of de geschiedenisbatch volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer of er geen geschiedenisregels zijn achtergebleven.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer expliciet of de teruggedraaide geschiedenisbatch
    // niet meer in de database bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $activeBatchDuringOperation->id,
    ]);

    // Controleer of er geen geschiedenisregels
    // voor de teruggedraaide geschiedenisbatch bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_change_batch_id' => $activeBatchDuringOperation->id,
    ]);

    // Controleer of de actieve geschiedenisbatch
    // na de mislukte bewerking is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    expect($historyDuringOperation->getActiveBatch())->toBeNull();

    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();

    // Controleer of de gerelateerde records na de rollback
    // volledig ongewijzigd zijn gebleven.
    expect($this->catalogEntry->fresh()->getAttributes())
        ->toBe($originalEntryAttributes);

    expect($this->stamp->fresh()->getAttributes())
        ->toBe($originalStampAttributes);

    expect($this->condition->fresh()->getAttributes())
        ->toBe($originalConditionAttributes);

    expect($this->edition->fresh()->getAttributes())
        ->toBe($originalEditionAttributes);

    expect($this->catalog->fresh()->getAttributes())
        ->toBe($originalCatalogAttributes);

    // Verplaats de testtijd tot na de mislukte controlebewerking.
    $this->travelTo($originalUpdatedAt->copy()->addMinutes(2));

    try {
        // Voer bewust een directe modelwijziging uit
        // zonder een nieuwe geschiedenisbatch te activeren.
        $restoredCatalogValue->update([
            'catalog_value' => 22.50,
        ]);

        // Haal de gewijzigde cataloguswaarde opnieuw op.
        $changedCatalogValue = $restoredCatalogValue->fresh();

        // Controleer of de latere modelwijziging succesvol is opgeslagen.
        expect($changedCatalogValue->catalog_value)
            ->toBe('22.50');

        // Controleer of de cataloguswaarde nog gecontroleerd moet worden.
        expect($changedCatalogValue->needs_review)
            ->toBeTrue();

        // Controleer of updated_at daadwerkelijk is gewijzigd.
        expect(
            $changedCatalogValue->updated_at
                ->greaterThan($originalUpdatedAt)
        )->toBeTrue();

        // Controleer of de overige catalogusgegevens behouden zijn.
        expect($changedCatalogValue->currency)
            ->toBe('EUR');

        expect($changedCatalogValue->notes)
            ->toBe('Oorspronkelijke notities bij de cataloguswaarde.');

        expect($changedCatalogValue->stamp_catalog_entry_id)
            ->toBe($this->catalogEntry->id);

        expect($changedCatalogValue->condition_id)
            ->toBe($this->condition->id);

        expect($changedCatalogValue->getAttributes()['created_at'])
            ->toBe($originalValueAttributes['created_at']);

        // Controleer of de latere modelwijziging
        // geen geschiedenisbatch heeft veroorzaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        // Controleer of de latere modelwijziging
        // geen geschiedenisregel heeft veroorzaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Controleer of de teruggedraaide geschiedenisbatch
        // nog steeds niet in de database bestaat.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $activeBatchDuringOperation->id,
        ]);

        // Controleer of er geen geschiedenisregel aan de
        // teruggedraaide geschiedenisbatch is gekoppeld.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $activeBatchDuringOperation->id,
        ]);

        // Controleer of er geen geschiedenisregel
        // voor de cataloguswaarde bestaat.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->catalogValue->id,
        ]);

        // Controleer of de geschiedenisbatch niet opnieuw actief is geworden.
        expect($history->getActiveBatch())->toBeNull();

        expect($historyDuringOperation->getActiveBatch())->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)->getActiveBatch()
        )->toBeNull();

        // Controleer of de tijdelijke foutlistener
        // niet opnieuw is uitgevoerd.
        expect($listenerCallCount)->toBe(1);

        // Controleer of de oorspronkelijke event dispatcher
        // nog steeds actief is.
        expect(StampCatalogValue::getEventDispatcher())
            ->toBe($originalDispatcher);

        // Controleer of de catalogusvermelding
        // volledig ongewijzigd is gebleven.
        expect($this->catalogEntry->fresh()->getAttributes())
            ->toBe($originalEntryAttributes);

        // Controleer of de postzegel volledig ongewijzigd is gebleven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampAttributes);

        // Controleer of de waarderingscategorie
        // volledig ongewijzigd is gebleven.
        expect($this->condition->fresh()->getAttributes())
            ->toBe($originalConditionAttributes);

        // Controleer of de cataloguseditie
        // volledig ongewijzigd is gebleven.
        expect($this->edition->fresh()->getAttributes())
            ->toBe($originalEditionAttributes);

        // Controleer of de catalogus volledig ongewijzigd is gebleven.
        expect($this->catalog->fresh()->getAttributes())
            ->toBe($originalCatalogAttributes);

        // Controleer of de beheerder volledig ongewijzigd is gebleven.
        expect($this->admin->fresh()->getAttributes())
            ->toBe($originalAdminAttributes);

        // Controleer of de gewone gebruiker
        // volledig ongewijzigd is gebleven.
        expect($this->user->fresh()->getAttributes())
            ->toBe($originalUserAttributes);

        // Controleer of de gebruikersrechten behouden zijn gebleven.
        expect($this->admin->fresh()->is_admin)->toBeTrue();

        expect($this->user->fresh()->is_admin)->toBeFalse();

        // Controleer of de latere cataloguswijziging
        // correct in de database aanwezig is.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->catalogValue->id,
            'stamp_catalog_entry_id' => $this->catalogEntry->id,
            'condition_id' => $this->condition->id,
            'catalog_value' => 22.50,
            'currency' => 'EUR',
            'notes' => 'Oorspronkelijke notities bij de cataloguswaarde.',
            'needs_review' => true,
            'created_at' => $originalValueAttributes['created_at'],
        ]);

        // Controleer of er geen ongewenste records
        // zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('countries', 1);
        $this->assertDatabaseCount('catalogs', 1);
        $this->assertDatabaseCount('catalog_editions', 1);
        $this->assertDatabaseCount('stamps', 1);
        $this->assertDatabaseCount('conditions', 1);
        $this->assertDatabaseCount('stamp_catalog_entries', 1);
        $this->assertDatabaseCount('stamp_catalog_values', 1);
        $this->assertDatabaseCount('series_catalog_values', 0);
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});
