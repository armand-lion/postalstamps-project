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

beforeEach(function () {
    // Gebruik Nederlands als standaardtaal voor de tests.
    app()->setLocale('nl');

    // Maak een gewone gebruiker aan die de bulkbewerking uitvoert.
    $this->user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een land aan voor de geschiedenisregistratietests.
    $this->country = Country::create([
        'name' => 'Testland Bulkbewerkingsgeschiedenis',
        'iso_code' => 'BG',
        'entity_type' => 'country',
        'slug' => 'testland-bulkbewerkingsgeschiedenis',
        'is_active' => true,
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Bulkbewerkingsgeschiedenis',
        'abbreviation' => 'BG',
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

    // Maak een algemene waarderingsregel aan.
    $this->conditionRule = CatalogConditionRule::create([
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
        'sort_order' => 1,
    ]);

    // Maak een postzegel aan binnen de geldigheidsperiode.
    $this->stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('een succesvolle bulkbewerking maakt precies een geschiedenisbatch aan', function () {
    // Controleer dat er vooraf geen catalogusgegevens aanwezig zijn.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Voer een succesvolle bulkbewerking uit.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-001',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer dat de batch aan de juiste cataloguseditie is gekoppeld.
    expect($batch->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer het bewerkingstype.
    expect($batch->operation)->toBe('bulk_edit');

    // Controleer de omschrijving van de bulkbewerking.
    expect($batch->description)
        ->toBe('Bulkbewerking van catalogusnummers en cataloguswaarden');

    // Controleer dat de geschiedenisbatch correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'operation' => 'bulk_edit',
        'description' => 'Bulkbewerking van catalogusnummers en cataloguswaarden',
    ]);

    // Controleer dat er precies twee geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 2);

    // Controleer dat beide geschiedenisregels bij dezelfde batch horen.
    expect($batch->logs()->count())->toBe(2);
});

test('de uitvoerende gebruiker wordt inclusief gebruikers-ID en gebruikersnaam correct vastgelegd in de geschiedenisbatch', function () {
    // Geef de uitvoerende gebruiker een herkenbare naam.
    $this->user->update([
        'name' => 'Testgebruiker Bulkbewerkingsgeschiedenis',
    ]);

    // Maak een tweede gebruiker aan die de bulkbewerking niet uitvoert.
    $otherUser = User::factory()->create([
        'name' => 'Andere Testgebruiker',
        'is_admin' => true,
    ]);

    // Controleer dat beide gebruikers verschillende ID's hebben.
    expect($this->user->id)->not->toBe($otherUser->id);

    // Controleer dat beide gebruikers verschillende namen hebben.
    expect($this->user->name)->not->toBe($otherUser->name);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Voer de bulkbewerking uit als de oorspronkelijke gebruiker.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-002',
                        'values' => [
                            $this->condition->id => '35.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer het gebruikers-ID van de uitvoerende gebruiker.
    expect($batch->user_id)->toBe($this->user->id);

    // Controleer de vastgelegde gebruikersnaam.
    expect($batch->user_name)
        ->toBe('Testgebruiker Bulkbewerkingsgeschiedenis');

    // Controleer dat het gebruikers-ID niet van de andere gebruiker is.
    expect($batch->user_id)->not->toBe($otherUser->id);

    // Controleer dat de gebruikersnaam niet van de andere gebruiker is.
    expect($batch->user_name)->not->toBe($otherUser->name);

    // Controleer de relatie met de uitvoerende gebruiker.
    expect($batch->user->id)->toBe($this->user->id);

    expect($batch->user->name)->toBe($this->user->name);

    // Controleer dat de gebruikersgegevens correct zijn opgeslagen.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'user_id' => $this->user->id,
        'user_name' => 'Testgebruiker Bulkbewerkingsgeschiedenis',
        'operation' => 'bulk_edit',
    ]);

    // Controleer dat er geen geschiedenisbatch voor de andere gebruiker bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'user_id' => $otherUser->id,
    ]);

    // Controleer dat de catalogusgegevens correct zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat beide geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 2);

    // Controleer dat beide geschiedenisregels bij dezelfde batch horen.
    expect($batch->logs()->count())->toBe(2);

    // Controleer dat beide gebruikers ongewijzigd behouden blijven.
    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Testgebruiker Bulkbewerkingsgeschiedenis',
        'is_admin' => false,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $otherUser->id,
        'name' => 'Andere Testgebruiker',
        'is_admin' => true,
    ]);
});

test('de catalogus- en cataloguseditiegegevens worden correct vastgelegd in de geschiedenisbatch', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere Testcatalogus Geschiedenis',
        'abbreviation' => 'AG',
    ]);

    // Koppel hetzelfde land aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie aan voor de tweede catalogus.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Andere Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Controleer dat beide catalogi verschillende ID's hebben.
    expect($this->catalog->id)->not->toBe($otherCatalog->id);

    // Controleer dat beide catalogi verschillende namen hebben.
    expect($this->catalog->name)->not->toBe($otherCatalog->name);

    // Controleer dat beide catalogusedities verschillende ID's hebben.
    expect($this->edition->id)->not->toBe($otherEdition->id);

    // Controleer dat beide catalogusedities verschillende namen hebben.
    expect($this->edition->edition_name)
        ->not->toBe($otherEdition->edition_name);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Voer een bulkbewerking uit binnen de oorspronkelijke cataloguseditie.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-003',
                        'values' => [
                            $this->condition->id => '45.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer het vastgelegde cataloguseditie-ID.
    expect($batch->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de vastgelegde catalogusnaam.
    expect($batch->catalog_name)
        ->toBe($this->catalog->name);

    // Controleer de vastgelegde cataloguseditienaam.
    expect($batch->catalog_edition_name)
        ->toBe($this->edition->edition_name);

    // Controleer dat de gegevens niet van de andere catalogus afkomstig zijn.
    expect($batch->catalog_name)
        ->not->toBe($otherCatalog->name);

    // Controleer dat de gegevens niet van de andere cataloguseditie afkomstig zijn.
    expect($batch->catalog_edition_id)
        ->not->toBe($otherEdition->id);

    expect($batch->catalog_edition_name)
        ->not->toBe($otherEdition->edition_name);

    // Controleer de relatie met de oorspronkelijke cataloguseditie.
    expect($batch->catalogEdition->id)
        ->toBe($this->edition->id);

    expect($batch->catalogEdition->edition_name)
        ->toBe($this->edition->edition_name);

    // Controleer de relatie met de oorspronkelijke catalogus.
    expect($batch->catalogEdition->catalog->id)
        ->toBe($this->catalog->id);

    expect($batch->catalogEdition->catalog->name)
        ->toBe($this->catalog->name);

    // Controleer dat de catalogus- en editiegegevens correct zijn opgeslagen.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'catalog_name' => $this->catalog->name,
        'catalog_edition_name' => $this->edition->edition_name,
        'operation' => 'bulk_edit',
    ]);

    // Controleer dat er geen geschiedenisbatch voor de andere editie bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'catalog_edition_id' => $otherEdition->id,
    ]);

    // Controleer dat de catalogusgegevens correct zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de andere cataloguseditie geen catalogusvermelding heeft gekregen.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $otherEdition->id,
    ]);

    // Controleer dat er precies twee geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 2);

    // Controleer dat beide geschiedenisregels bij dezelfde batch horen.
    expect($batch->logs()->count())->toBe(2);

    // Controleer dat beide geschiedenisregels bij de oorspronkelijke editie horen.
    expect(
        $batch->logs()
            ->where('catalog_edition_id', $this->edition->id)
            ->count()
    )->toBe(2);

    // Controleer dat er geen geschiedenisregels voor de andere editie bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_edition_id' => $otherEdition->id,
    ]);

    // Controleer dat beide catalogi ongewijzigd behouden blijven.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    $this->assertDatabaseHas('catalogs', [
        'id' => $otherCatalog->id,
        'name' => $otherCatalog->name,
    ]);

    // Controleer dat beide catalogusedities ongewijzigd behouden blijven.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => $this->edition->edition_name,
    ]);

    $this->assertDatabaseHas('catalog_editions', [
        'id' => $otherEdition->id,
        'catalog_id' => $otherCatalog->id,
        'edition_name' => $otherEdition->edition_name,
    ]);
});

test('het aanmaken van een catalogusvermelding wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Controleer dat er vooraf geen catalogusgegevens aanwezig zijn.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Maak via een bulkbewerking uitsluitend een catalogusvermelding aan.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-004',
                        'values' => [],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er precies één catalogusvermelding is aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat er geen cataloguswaarden zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = StampCatalogEntry::query()->sole();

    // Controleer de gegevens van de catalogusvermelding.
    expect($catalogEntry->catalog_edition_id)
        ->toBe($this->edition->id);

    expect($catalogEntry->stamp_id)
        ->toBe($this->stamp->id);

    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-004');

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer dat er geen koppeling met een waarderingscategorie bestaat.
    expect($log->condition_id)->toBeNull();

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_entry');

    // Controleer het ID van de aangemaakte catalogusvermelding.
    expect($log->record_id)
        ->toBe($catalogEntry->id);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('created');

    // Controleer dat er geen oude waarden aanwezig zijn.
    expect($log->old_values)->toBeNull();

    // Controleer dat de nieuwe waarden als array beschikbaar zijn.
    expect($log->new_values)->toBeArray();

    // Controleer het ID in de nieuwe waarden.
    expect($log->new_values['id'])
        ->toBe($catalogEntry->id);

    // Controleer het cataloguseditie-ID in de nieuwe waarden.
    expect($log->new_values['catalog_edition_id'])
        ->toBe($this->edition->id);

    // Controleer het postzegel-ID in de nieuwe waarden.
    expect($log->new_values['stamp_id'])
        ->toBe($this->stamp->id);

    // Controleer het catalogusnummer in de nieuwe waarden.
    expect($log->new_values['catalog_number'])
        ->toBe('HISTORY-004');

    // Controleer dat de aanmaakdatum is vastgelegd.
    expect($log->new_values)->toHaveKey('created_at');
    expect($log->new_values['created_at'])->not->toBeNull();

    // Controleer dat de wijzigingsdatum is vastgelegd.
    expect($log->new_values)->toHaveKey('updated_at');
    expect($log->new_values['updated_at'])->not->toBeNull();

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => $catalogEntry->id,
        'action' => 'created',
        'old_values' => null,
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor een cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het aanmaken van een cataloguswaarde wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-005',
    ]);

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Voeg via een bulkbewerking een cataloguswaarde toe.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-005',
                        'values' => [
                            $this->condition->id => '55.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er geen extra catalogusvermelding is aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat er precies één cataloguswaarde is aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Haal de aangemaakte cataloguswaarde op.
    $catalogValue = StampCatalogValue::query()->sole();

    // Controleer de koppeling met de bestaande catalogusvermelding.
    expect($catalogValue->stamp_catalog_entry_id)
        ->toBe($catalogEntry->id);

    // Controleer de koppeling met de waarderingscategorie.
    expect($catalogValue->condition_id)
        ->toBe($this->condition->id);

    // Controleer de opgeslagen cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('55.00');

    // Controleer de standaardvaluta.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de nieuwe cataloguswaarde geen controlemarkering heeft.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer de koppeling met de waarderingscategorie.
    expect($log->condition_id)
        ->toBe($this->condition->id);

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_value');

    // Controleer het ID van de aangemaakte cataloguswaarde.
    expect($log->record_id)
        ->toBe($catalogValue->id);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('created');

    // Controleer dat er geen oude waarden aanwezig zijn.
    expect($log->old_values)->toBeNull();

    // Controleer dat de nieuwe waarden als array beschikbaar zijn.
    expect($log->new_values)->toBeArray();

    // Controleer het ID in de nieuwe waarden.
    expect($log->new_values['id'])
        ->toBe($catalogValue->id);

    // Controleer het catalogusvermelding-ID in de nieuwe waarden.
    expect($log->new_values['stamp_catalog_entry_id'])
        ->toBe($catalogEntry->id);

    // Controleer het waarderingscategorie-ID in de nieuwe waarden.
    expect($log->new_values['condition_id'])
        ->toBe($this->condition->id);

    // Controleer de cataloguswaarde in de nieuwe waarden.
    expect((float) $log->new_values['catalog_value'])
        ->toBe(55.0);

    // Controleer de valuta in de nieuwe waarden.
    expect($log->new_values['currency'])
        ->toBe('EUR');

    // Controleer dat de aanmaakdatum is vastgelegd.
    expect($log->new_values)->toHaveKey('created_at');
    expect($log->new_values['created_at'])->not->toBeNull();

    // Controleer dat de wijzigingsdatum is vastgelegd.
    expect($log->new_values)->toHaveKey('updated_at');
    expect($log->new_values['updated_at'])->not->toBeNull();

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => $this->condition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $catalogValue->id,
        'action' => 'created',
        'old_values' => null,
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer de relatie met de waarderingscategorie.
    expect($log->condition->id)
        ->toBe($this->condition->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor de catalogusvermelding bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_entry',
    ]);

    // Controleer dat de bestaande catalogusvermelding ongewijzigd blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-005',
    ]);

    // Controleer dat de cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '55.00',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het wijzigen van een bestaand catalogusnummer wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-006-OLD',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '65.00',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-006-OLD');

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('65.00');

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Wijzig uitsluitend het catalogusnummer via een bulkbewerking.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-006-NEW',
                        'values' => [
                            $this->condition->id => '65.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er geen extra catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Haal de actuele catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer het gewijzigde catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-006-NEW');

    // Controleer dat de cataloguswaarde ongewijzigd blijft.
    expect($catalogValue->catalog_value)->toBe('65.00');

    // Controleer dat de valuta ongewijzigd blijft.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat er geen controlemarkering is toegevoegd.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer dat er geen koppeling met een waarderingscategorie bestaat.
    expect($log->condition_id)->toBeNull();

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_entry');

    // Controleer het ID van de gewijzigde catalogusvermelding.
    expect($log->record_id)
        ->toBe($catalogEntry->id);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('updated');

    // Controleer dat de oude en nieuwe waarden als arrays beschikbaar zijn.
    expect($log->old_values)->toBeArray();
    expect($log->new_values)->toBeArray();

    // Controleer het oorspronkelijke catalogusnummer in de oude waarden.
    expect($log->old_values['catalog_number'])
        ->toBe('HISTORY-006-OLD');

    // Controleer het gewijzigde catalogusnummer in de nieuwe waarden.
    expect($log->new_values['catalog_number'])
        ->toBe('HISTORY-006-NEW');

    // Controleer dat de oude en nieuwe catalogusnummers verschillen.
    expect($log->old_values['catalog_number'])
        ->not->toBe($log->new_values['catalog_number']);

    // Controleer dat uitsluitend het gewijzigde veld is geregistreerd.
    expect($log->old_values)->toBe([
        'catalog_number' => 'HISTORY-006-OLD',
    ]);

    expect($log->new_values)->toBe([
        'catalog_number' => 'HISTORY-006-NEW',
    ]);

    // Controleer dat de wijzigingsdatum niet als inhoudelijke wijziging is geregistreerd.
    expect($log->old_values)->not->toHaveKey('updated_at');
    expect($log->new_values)->not->toHaveKey('updated_at');

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => $catalogEntry->id,
        'action' => 'updated',
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor de cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte verwijderingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'deleted',
    ]);

    // Controleer dat het gewijzigde catalogusnummer correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-006-NEW',
    ]);

    // Controleer dat het oorspronkelijke catalogusnummer niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_number' => 'HISTORY-006-OLD',
    ]);

    // Controleer dat de bestaande cataloguswaarde ongewijzigd blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '65.00',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het wijzigen van een bestaande cataloguswaarde wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-007',
    ]);

    // Maak een bestaande cataloguswaarde aan zonder controlemarkering.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '75.00',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('75.00');

    // Controleer de oorspronkelijke valuta.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de oorspronkelijke controlemarkering niet actief is.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Wijzig uitsluitend de cataloguswaarde via een bulkbewerking.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-007',
                        'values' => [
                            $this->condition->id => '87.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er geen extra catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Haal de actuele catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat het catalogusnummer ongewijzigd blijft.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-007');

    // Controleer de gewijzigde cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('87.50');

    // Controleer dat de valuta ongewijzigd blijft.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de waarderingscategorie ongewijzigd blijft.
    expect($catalogValue->condition_id)
        ->toBe($this->condition->id);

    // Controleer dat de controlemarkering automatisch is geactiveerd.
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer de koppeling met de waarderingscategorie.
    expect($log->condition_id)
        ->toBe($this->condition->id);

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_value');

    // Controleer het ID van de gewijzigde cataloguswaarde.
    expect($log->record_id)
        ->toBe($catalogValue->id);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('updated');

    // Controleer dat de oude en nieuwe waarden als arrays beschikbaar zijn.
    expect($log->old_values)->toBeArray();
    expect($log->new_values)->toBeArray();

    // Controleer dat beide gewijzigde velden aanwezig zijn.
    expect($log->old_values)->toHaveKeys([
        'catalog_value',
        'needs_review',
    ]);

    expect($log->new_values)->toHaveKeys([
        'catalog_value',
        'needs_review',
    ]);

    // Controleer de oorspronkelijke cataloguswaarde in de oude waarden.
    expect((float) $log->old_values['catalog_value'])
        ->toBe(75.0);

    // Controleer de gewijzigde cataloguswaarde in de nieuwe waarden.
    expect((float) $log->new_values['catalog_value'])
        ->toBe(87.5);

    // Controleer de oorspronkelijke controlemarkering.
    expect($log->old_values['needs_review'])
        ->toBeIn([false, 0, '0']);

    // Controleer de gewijzigde controlemarkering.
    expect($log->new_values['needs_review'])
        ->toBeIn([true, 1, '1']);

    // Controleer dat uitsluitend de twee gewijzigde velden zijn geregistreerd.
    expect($log->old_values)->toHaveCount(2);
    expect($log->new_values)->toHaveCount(2);

    // Controleer dat de wijzigingsdatum niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('updated_at');
    expect($log->new_values)->not->toHaveKey('updated_at');

    // Controleer dat de ongewijzigde valuta niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('currency');
    expect($log->new_values)->not->toHaveKey('currency');

    // Controleer dat de ongewijzigde waarderingscategorie niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('condition_id');
    expect($log->new_values)->not->toHaveKey('condition_id');

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => $this->condition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $catalogValue->id,
        'action' => 'updated',
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer de relatie met de waarderingscategorie.
    expect($log->condition->id)
        ->toBe($this->condition->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor de catalogusvermelding bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_entry',
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte verwijderingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'deleted',
    ]);

    // Controleer dat de bestaande catalogusvermelding ongewijzigd blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-007',
    ]);

    // Controleer dat de gewijzigde cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '87.50',
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'catalog_value' => '75.00',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het wijzigen van een cataloguswaarde met een actieve controlemarkering registreert uitsluitend de gewijzigde cataloguswaarde', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-008',
    ]);

    // Maak een bestaande cataloguswaarde aan met een actieve controlemarkering.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '85.00',
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('85.00');

    // Controleer de oorspronkelijke valuta.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de controlemarkering vooraf al actief is.
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Wijzig uitsluitend de cataloguswaarde via een bulkbewerking.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-008',
                        'values' => [
                            $this->condition->id => '97.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat er geen extra catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Haal de actuele catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat het catalogusnummer ongewijzigd blijft.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-008');

    // Controleer de gewijzigde cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('97.50');

    // Controleer dat de valuta ongewijzigd blijft.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de waarderingscategorie ongewijzigd blijft.
    expect($catalogValue->condition_id)
        ->toBe($this->condition->id);

    // Controleer dat de controlemarkering actief blijft.
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer de koppeling met de waarderingscategorie.
    expect($log->condition_id)
        ->toBe($this->condition->id);

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_value');

    // Controleer het ID van de gewijzigde cataloguswaarde.
    expect($log->record_id)
        ->toBe($catalogValue->id);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('updated');

    // Controleer dat de oude en nieuwe waarden als arrays beschikbaar zijn.
    expect($log->old_values)->toBeArray();
    expect($log->new_values)->toBeArray();

    // Controleer dat het gewijzigde veld aanwezig is.
    expect($log->old_values)->toHaveKey('catalog_value');
    expect($log->new_values)->toHaveKey('catalog_value');

    // Controleer de oorspronkelijke cataloguswaarde in de oude waarden.
    expect((float) $log->old_values['catalog_value'])
        ->toBe(85.0);

    // Controleer de gewijzigde cataloguswaarde in de nieuwe waarden.
    expect((float) $log->new_values['catalog_value'])
        ->toBe(97.5);

    // Controleer dat uitsluitend de cataloguswaarde is geregistreerd.
    expect($log->old_values)->toHaveCount(1);
    expect($log->new_values)->toHaveCount(1);

    // Controleer dat de ongewijzigde controlemarkering niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('needs_review');
    expect($log->new_values)->not->toHaveKey('needs_review');

    // Controleer dat de wijzigingsdatum niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('updated_at');
    expect($log->new_values)->not->toHaveKey('updated_at');

    // Controleer dat de ongewijzigde valuta niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('currency');
    expect($log->new_values)->not->toHaveKey('currency');

    // Controleer dat de ongewijzigde waarderingscategorie niet is geregistreerd.
    expect($log->old_values)->not->toHaveKey('condition_id');
    expect($log->new_values)->not->toHaveKey('condition_id');

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => $this->condition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $catalogValue->id,
        'action' => 'updated',
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer de relatie met de waarderingscategorie.
    expect($log->condition->id)
        ->toBe($this->condition->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor de catalogusvermelding bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_entry',
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte verwijderingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'deleted',
    ]);

    // Controleer dat de bestaande catalogusvermelding ongewijzigd blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-008',
    ]);

    // Controleer dat de gewijzigde cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '97.50',
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'catalog_value' => '85.00',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het verwijderen van een bestaande cataloguswaarde wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-009',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '95.00',
        'currency' => 'EUR',
        'notes' => 'Test verwijderen cataloguswaarde',
        'needs_review' => true,
    ]);

    // Haal alle opgeslagen attributen opnieuw op.
    $catalogValue->refresh();

    // Bewaar de oorspronkelijke recordgegevens voor de geschiedeniscontrole.
    $originalAttributes = $catalogValue->getAttributes();

    // Bewaar het ID van de te verwijderen cataloguswaarde.
    $catalogValueId = $catalogValue->id;

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('95.00');

    // Controleer de oorspronkelijke valuta.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer de oorspronkelijke notities.
    expect($catalogValue->notes)
        ->toBe('Test verwijderen cataloguswaarde');

    // Controleer dat de controlemarkering actief is.
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Verwijder uitsluitend de cataloguswaarde via een bulkbewerking.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-009',
                        'values' => [
                            $this->condition->id => null,
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding behouden blijft.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat de cataloguswaarde is verwijderd.
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat het oorspronkelijke record niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValueId,
    ]);

    // Controleer dat de cataloguswaarde niet meer via Eloquent beschikbaar is.
    expect(StampCatalogValue::find($catalogValueId))->toBeNull();

    // Controleer dat de catalogusvermelding geen cataloguswaarden meer bevat.
    expect($catalogEntry->values()->count())->toBe(0);

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer de koppeling met de waarderingscategorie.
    expect($log->condition_id)
        ->toBe($this->condition->id);

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_value');

    // Controleer dat het ID van de verwijderde cataloguswaarde behouden blijft.
    expect($log->record_id)->toBe($catalogValueId);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('deleted');

    // Controleer dat de oorspronkelijke waarden als array beschikbaar zijn.
    expect($log->old_values)->toBeArray();

    // Controleer dat er geen nieuwe waarden aanwezig zijn.
    expect($log->new_values)->toBeNull();

    // Controleer het oorspronkelijke record-ID.
    expect($log->old_values['id'])->toBe($catalogValueId);

    // Controleer het oorspronkelijke catalogusvermelding-ID.
    expect($log->old_values['stamp_catalog_entry_id'])
        ->toBe($catalogEntry->id);

    // Controleer het oorspronkelijke waarderingscategorie-ID.
    expect($log->old_values['condition_id'])
        ->toBe($this->condition->id);

    // Controleer de oorspronkelijke cataloguswaarde.
    expect((float) $log->old_values['catalog_value'])
        ->toBe(95.0);

    // Controleer de oorspronkelijke valuta.
    expect($log->old_values['currency'])->toBe('EUR');

    // Controleer de oorspronkelijke notities.
    expect($log->old_values['notes'])
        ->toBe('Test verwijderen cataloguswaarde');

    // Controleer de oorspronkelijke controlemarkering.
    expect($log->old_values['needs_review'])
        ->toBeIn([true, 1, '1']);

    // Controleer dat de oorspronkelijke aanmaakdatum behouden blijft.
    expect($log->old_values)->toHaveKey('created_at');
    expect($log->old_values['created_at'])->not->toBeNull();

    // Controleer dat de oorspronkelijke wijzigingsdatum behouden blijft.
    expect($log->old_values)->toHaveKey('updated_at');
    expect($log->old_values['updated_at'])->not->toBeNull();

    // Controleer dat alle oorspronkelijke attributen zijn geregistreerd.
    expect($log->old_values)
        ->toHaveCount(count($originalAttributes));

    // Controleer alle oorspronkelijke recordgegevens.
    foreach ($originalAttributes as $attribute => $originalValue) {
        expect($log->old_values)->toHaveKey($attribute);
        expect($log->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => $this->condition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $catalogValueId,
        'action' => 'deleted',
        'new_values' => null,
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer de relatie met de waarderingscategorie.
    expect($log->condition->id)
        ->toBe($this->condition->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor de catalogusvermelding bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_entry',
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte wijzigingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-009',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('het verwijderen van een catalogusvermelding zonder cataloguswaarden wordt correct geregistreerd in de catalogusgeschiedenis', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-010',
        'notes' => 'Test verwijderen catalogusvermelding',
    ]);

    // Haal alle opgeslagen attributen opnieuw op.
    $catalogEntry->refresh();

    // Bewaar de oorspronkelijke recordgegevens voor de geschiedeniscontrole.
    $originalAttributes = $catalogEntry->getAttributes();

    // Bewaar het ID van de te verwijderen catalogusvermelding.
    $catalogEntryId = $catalogEntry->id;

    // Controleer de uitgangssituatie van de catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-010');

    // Controleer de oorspronkelijke notities.
    expect($catalogEntry->notes)
        ->toBe('Test verwijderen catalogusvermelding');

    // Controleer dat er geen cataloguswaarden gekoppeld zijn.
    expect($catalogEntry->values()->count())->toBe(0);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Verwijder de catalogusvermelding via een bulkbewerking.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => '',
                        'values' => [],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding is verwijderd.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);

    // Controleer dat er nog steeds geen cataloguswaarden aanwezig zijn.
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat het oorspronkelijke record niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntryId,
    ]);

    // Controleer dat de catalogusvermelding niet meer via Eloquent beschikbaar is.
    expect(StampCatalogEntry::find($catalogEntryId))->toBeNull();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies één geschiedenisregel is aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Haal de geschiedenisbatch en geschiedenisregel op.
    $batch = CatalogChangeBatch::query()->sole();
    $log = CatalogChangeLog::query()->sole();

    // Controleer de koppeling met de geschiedenisbatch.
    expect($log->catalog_change_batch_id)
        ->toBe($batch->id);

    // Controleer de koppeling met de cataloguseditie.
    expect($log->catalog_edition_id)
        ->toBe($this->edition->id);

    // Controleer de koppeling met de postzegel.
    expect($log->stamp_id)
        ->toBe($this->stamp->id);

    // Controleer dat er geen koppeling met een postzegelserie bestaat.
    expect($log->stamp_series_id)->toBeNull();

    // Controleer dat er geen koppeling met een waarderingscategorie bestaat.
    expect($log->condition_id)->toBeNull();

    // Controleer het recordtype.
    expect($log->record_type)
        ->toBe('stamp_catalog_entry');

    // Controleer dat het ID van de verwijderde catalogusvermelding behouden blijft.
    expect($log->record_id)->toBe($catalogEntryId);

    // Controleer het geregistreerde actietype.
    expect($log->action)->toBe('deleted');

    // Controleer dat de oorspronkelijke waarden als array beschikbaar zijn.
    expect($log->old_values)->toBeArray();

    // Controleer dat er geen nieuwe waarden aanwezig zijn.
    expect($log->new_values)->toBeNull();

    // Controleer het oorspronkelijke record-ID.
    expect($log->old_values['id'])->toBe($catalogEntryId);

    // Controleer het oorspronkelijke cataloguseditie-ID.
    expect($log->old_values['catalog_edition_id'])
        ->toBe($this->edition->id);

    // Controleer het oorspronkelijke postzegel-ID.
    expect($log->old_values['stamp_id'])
        ->toBe($this->stamp->id);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($log->old_values['catalog_number'])
        ->toBe('HISTORY-010');

    // Controleer de oorspronkelijke notities.
    expect($log->old_values['notes'])
        ->toBe('Test verwijderen catalogusvermelding');

    // Controleer dat de oorspronkelijke aanmaakdatum behouden blijft.
    expect($log->old_values)->toHaveKey('created_at');
    expect($log->old_values['created_at'])->not->toBeNull();

    // Controleer dat de oorspronkelijke wijzigingsdatum behouden blijft.
    expect($log->old_values)->toHaveKey('updated_at');
    expect($log->old_values['updated_at'])->not->toBeNull();

    // Controleer dat alle oorspronkelijke attributen zijn geregistreerd.
    expect($log->old_values)
        ->toHaveCount(count($originalAttributes));

    // Controleer alle oorspronkelijke recordgegevens.
    foreach ($originalAttributes as $attribute => $originalValue) {
        expect($log->old_values)->toHaveKey($attribute);
        expect($log->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer dat de geschiedenisregel correct is opgeslagen.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'stamp_series_id' => null,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => $catalogEntryId,
        'action' => 'deleted',
        'new_values' => null,
    ]);

    // Controleer de relatie met de geschiedenisbatch.
    expect($log->batch->id)->toBe($batch->id);

    // Controleer de relatie met de cataloguseditie.
    expect($log->catalogEdition->id)
        ->toBe($this->edition->id);

    // Controleer de relatie met de postzegel.
    expect($log->stamp->id)
        ->toBe($this->stamp->id);

    // Controleer dat de batch precies één geschiedenisregel bevat.
    expect($batch->logs()->count())->toBe(1);

    // Controleer dat er geen geschiedenisregel voor een cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte wijzigingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
    ]);

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
        'slug' => 'postfris',
    ]);
});

test('het verwijderen van een catalogusvermelding met meerdere cataloguswaarden wordt binnen dezelfde geschiedenisbatch in de juiste volgorde geregistreerd', function () {
    // Maak een tweede waarderingscategorie aan.
    $secondCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak een algemene waarderingsregel voor de tweede waarderingscategorie.
    $secondConditionRule = CatalogConditionRule::create([
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $secondCondition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
        'sort_order' => 2,
    ]);

    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-011',
        'notes' => 'Test gecombineerde verwijdering',
    ]);

    // Maak de eerste cataloguswaarde aan.
    $firstCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '110.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde',
        'needs_review' => false,
    ]);

    // Maak de tweede cataloguswaarde aan.
    $secondCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $secondCondition->id,
        'catalog_value' => '45.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde cataloguswaarde',
        'needs_review' => true,
    ]);

    // Haal alle oorspronkelijke recordgegevens opnieuw op.
    $catalogEntry->refresh();
    $firstCatalogValue->refresh();
    $secondCatalogValue->refresh();

    // Bewaar alle oorspronkelijke attributen voor de geschiedeniscontrole.
    $originalEntryAttributes = $catalogEntry->getAttributes();
    $originalFirstValueAttributes = $firstCatalogValue->getAttributes();
    $originalSecondValueAttributes = $secondCatalogValue->getAttributes();

    // Bewaar de oorspronkelijke record-ID's.
    $catalogEntryId = $catalogEntry->id;
    $firstCatalogValueId = $firstCatalogValue->id;
    $secondCatalogValueId = $secondCatalogValue->id;

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 2);
    $this->assertDatabaseCount('conditions', 2);
    $this->assertDatabaseCount('catalog_condition_rules', 2);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-011');

    // Controleer beide oorspronkelijke cataloguswaarden.
    expect($firstCatalogValue->catalog_value)->toBe('110.00');
    expect($secondCatalogValue->catalog_value)->toBe('45.00');

    // Controleer beide oorspronkelijke controlemarkeringen.
    expect($firstCatalogValue->needs_review)->toBeFalse();
    expect($secondCatalogValue->needs_review)->toBeTrue();

    // Controleer dat beide cataloguswaarden aan dezelfde vermelding gekoppeld zijn.
    expect($firstCatalogValue->stamp_catalog_entry_id)
        ->toBe($catalogEntryId);

    expect($secondCatalogValue->stamp_catalog_entry_id)
        ->toBe($catalogEntryId);

    // Controleer dat beide cataloguswaarden verschillende waarderingscategorieën hebben.
    expect($firstCatalogValue->condition_id)
        ->not->toBe($secondCatalogValue->condition_id);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Verwijder de volledige catalogusvermelding inclusief beide cataloguswaarden.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => '',
                        'values' => [
                            $this->condition->id => null,
                            $secondCondition->id => null,
                        ],
                    ],
                ],
            ]
        );

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding volledig is verwijderd.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);

    // Controleer dat beide cataloguswaarden volledig zijn verwijderd.
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat de oorspronkelijke catalogusvermelding niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntryId,
    ]);

    // Controleer dat beide oorspronkelijke cataloguswaarden niet meer bestaan.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $firstCatalogValueId,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $secondCatalogValueId,
    ]);

    // Controleer dat de verwijderde records niet meer via Eloquent beschikbaar zijn.
    expect(StampCatalogEntry::find($catalogEntryId))->toBeNull();
    expect(StampCatalogValue::find($firstCatalogValueId))->toBeNull();
    expect(StampCatalogValue::find($secondCatalogValueId))->toBeNull();

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies drie geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 3);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de gegevens van de geschiedenisbatch.
    expect($batch->catalog_edition_id)->toBe($this->edition->id);
    expect($batch->user_id)->toBe($this->user->id);
    expect($batch->operation)->toBe('bulk_edit');

    // Haal de geschiedenisregels op in registratievolgorde.
    $logs = $batch->logs()
        ->orderBy('id')
        ->get();

    // Controleer dat alle drie de geschiedenisregels bij dezelfde batch horen.
    expect($logs)->toHaveCount(3);

    // Controleer de gemeenschappelijke gegevens van alle geschiedenisregels.
    foreach ($logs as $log) {
        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($this->edition->id);
        expect($log->stamp_id)->toBe($this->stamp->id);
        expect($log->stamp_series_id)->toBeNull();
        expect($log->action)->toBe('deleted');
        expect($log->old_values)->toBeArray();
        expect($log->new_values)->toBeNull();
        expect($log->batch->id)->toBe($batch->id);
        expect($log->catalogEdition->id)->toBe($this->edition->id);
        expect($log->stamp->id)->toBe($this->stamp->id);
    }

    // Controleer de volgorde van de geregistreerde recordtypen.
    expect($logs->pluck('record_type')->all())->toBe([
        'stamp_catalog_value',
        'stamp_catalog_value',
        'stamp_catalog_entry',
    ]);

    // Haal de geschiedenisregel van de eerste cataloguswaarde op.
    $firstValueLog = $logs
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $firstCatalogValueId)
        ->sole();

    // Haal de geschiedenisregel van de tweede cataloguswaarde op.
    $secondValueLog = $logs
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $secondCatalogValueId)
        ->sole();

    // Haal de geschiedenisregel van de catalogusvermelding op.
    $entryLog = $logs
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $catalogEntryId)
        ->sole();

    // Controleer dat beide cataloguswaarden vóór de catalogusvermelding zijn geregistreerd.
    expect($firstValueLog->id)->toBeLessThan($entryLog->id);
    expect($secondValueLog->id)->toBeLessThan($entryLog->id);

    // Controleer dat de catalogusvermelding als laatste is geregistreerd.
    expect($logs->last()->id)->toBe($entryLog->id);

    // Controleer de waarderingscategorie van de eerste cataloguswaarde.
    expect($firstValueLog->condition_id)
        ->toBe($this->condition->id);

    // Controleer de waarderingscategorie van de tweede cataloguswaarde.
    expect($secondValueLog->condition_id)
        ->toBe($secondCondition->id);

    // Controleer dat de catalogusvermelding geen waarderingscategorie heeft.
    expect($entryLog->condition_id)->toBeNull();

    // Controleer de relaties met beide waarderingscategorieën.
    expect($firstValueLog->condition->id)
        ->toBe($this->condition->id);

    expect($secondValueLog->condition->id)
        ->toBe($secondCondition->id);

    // Controleer de oorspronkelijke cataloguswaarden.
    expect((float) $firstValueLog->old_values['catalog_value'])
        ->toBe(110.0);

    expect((float) $secondValueLog->old_values['catalog_value'])
        ->toBe(45.0);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($entryLog->old_values['catalog_number'])
        ->toBe('HISTORY-011');

    // Controleer de volledige oorspronkelijke attributen van de eerste cataloguswaarde.
    expect($firstValueLog->old_values)
        ->toHaveCount(count($originalFirstValueAttributes));

    foreach ($originalFirstValueAttributes as $attribute => $originalValue) {
        expect($firstValueLog->old_values)->toHaveKey($attribute);
        expect($firstValueLog->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer de volledige oorspronkelijke attributen van de tweede cataloguswaarde.
    expect($secondValueLog->old_values)
        ->toHaveCount(count($originalSecondValueAttributes));

    foreach ($originalSecondValueAttributes as $attribute => $originalValue) {
        expect($secondValueLog->old_values)->toHaveKey($attribute);
        expect($secondValueLog->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer de volledige oorspronkelijke attributen van de catalogusvermelding.
    expect($entryLog->old_values)
        ->toHaveCount(count($originalEntryAttributes));

    foreach ($originalEntryAttributes as $attribute => $originalValue) {
        expect($entryLog->old_values)->toHaveKey($attribute);
        expect($entryLog->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer de geschiedenisregistratie van de eerste cataloguswaarde.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $firstValueLog->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'condition_id' => $this->condition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $firstCatalogValueId,
        'action' => 'deleted',
        'new_values' => null,
    ]);

    // Controleer de geschiedenisregistratie van de tweede cataloguswaarde.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $secondValueLog->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'condition_id' => $secondCondition->id,
        'record_type' => 'stamp_catalog_value',
        'record_id' => $secondCatalogValueId,
        'action' => 'deleted',
        'new_values' => null,
    ]);

    // Controleer de geschiedenisregistratie van de catalogusvermelding.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $entryLog->id,
        'catalog_change_batch_id' => $batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => $catalogEntryId,
        'action' => 'deleted',
        'new_values' => null,
    ]);

    // Controleer dat er precies twee verwijderde cataloguswaarden zijn geregistreerd.
    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('action', 'deleted')
            ->count()
    )->toBe(2);

    // Controleer dat er precies één verwijderde catalogusvermelding is geregistreerd.
    expect(
        $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('action', 'deleted')
            ->count()
    )->toBe(1);

    // Controleer dat er geen onterechte aanmaakregistraties bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte wijzigingsregistraties bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
    ]);

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    // Controleer dat beide waarderingscategorieën behouden blijven.
    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
        'slug' => 'postfris',
    ]);

    $this->assertDatabaseHas('conditions', [
        'id' => $secondCondition->id,
        'slug' => 'gestempeld',
    ]);

    // Controleer dat beide waarderingsregels behouden blijven.
    $this->assertDatabaseCount('catalog_condition_rules', 2);

    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);

    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $secondConditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $secondCondition->id,
    ]);
});

test('een volledig ongewijzigde bulkbewerking veroorzaakt geen geschiedenisregels of lege geschiedenisbatch', function () {
    // Maak vooraf een catalogusvermelding aan zonder actieve geschiedenisbatch.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-012',
        'notes' => 'Ongewijzigde catalogusvermelding',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '125.00',
        'currency' => 'EUR',
        'notes' => 'Ongewijzigde cataloguswaarde',
        'needs_review' => false,
    ]);

    // Haal alle opgeslagen attributen opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Bewaar alle oorspronkelijke attributen voor de vergelijking.
    $originalEntryAttributes = $catalogEntry->getAttributes();
    $originalValueAttributes = $catalogValue->getAttributes();

    // Bewaar de oorspronkelijke record-ID's.
    $catalogEntryId = $catalogEntry->id;
    $catalogValueId = $catalogValue->id;

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-012');

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('125.00');

    // Controleer de oorspronkelijke valuta.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de controlemarkering niet actief is.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Verplaats de testtijd zodat ongewenste timestampwijzigingen zichtbaar worden.
    $this->travel(1)->minute();

    // Sla exact dezelfde catalogusgegevens opnieuw op.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'HISTORY-012',
                        'values' => [
                            $this->condition->id => '125.00',
                        ],
                    ],
                ],
            ]
        );

    // Herstel de oorspronkelijke testtijd.
    $this->travelBack();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Haal beide records opnieuw op uit de database.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat er geen dubbele catalogusvermelding is aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat er geen dubbele cataloguswaarde is aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat beide oorspronkelijke record-ID's behouden blijven.
    expect($catalogEntry->id)->toBe($catalogEntryId);
    expect($catalogValue->id)->toBe($catalogValueId);

    // Controleer dat het catalogusnummer ongewijzigd is.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-012');

    // Controleer dat de cataloguswaarde ongewijzigd is.
    expect($catalogValue->catalog_value)->toBe('125.00');

    // Controleer dat de valuta ongewijzigd is.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de notities van de catalogusvermelding behouden blijven.
    expect($catalogEntry->notes)
        ->toBe('Ongewijzigde catalogusvermelding');

    // Controleer dat de notities van de cataloguswaarde behouden blijven.
    expect($catalogValue->notes)
        ->toBe('Ongewijzigde cataloguswaarde');

    // Controleer dat de controlemarkering niet onterecht is geactiveerd.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat alle oorspronkelijke attributen van de vermelding behouden blijven.
    expect($catalogEntry->getAttributes())
        ->toBe($originalEntryAttributes);

    // Controleer dat alle oorspronkelijke attributen van de waarde behouden blijven.
    expect($catalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer expliciet de oorspronkelijke timestamps van de vermelding.
    expect($catalogEntry->getRawOriginal('created_at'))
        ->toBe($originalEntryAttributes['created_at']);

    expect($catalogEntry->getRawOriginal('updated_at'))
        ->toBe($originalEntryAttributes['updated_at']);

    // Controleer expliciet de oorspronkelijke timestamps van de cataloguswaarde.
    expect($catalogValue->getRawOriginal('created_at'))
        ->toBe($originalValueAttributes['created_at']);

    expect($catalogValue->getRawOriginal('updated_at'))
        ->toBe($originalValueAttributes['updated_at']);

    // Controleer dat er geen geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat er geen lege geschiedenisbatch is achtergebleven.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat er geen geschiedenisbatch voor deze editie bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'catalog_edition_id' => $this->edition->id,
    ]);

    // Controleer dat er geen geschiedenisbatch voor deze gebruiker bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'user_id' => $this->user->id,
    ]);

    // Controleer dat er geen geschiedenisregel voor de catalogusvermelding bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_entry',
        'record_id' => $catalogEntryId,
    ]);

    // Controleer dat er geen geschiedenisregel voor de cataloguswaarde bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'record_type' => 'stamp_catalog_value',
        'record_id' => $catalogValueId,
    ]);

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte wijzigingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Controleer dat er geen onterechte verwijderingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'deleted',
    ]);

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntryId,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-012',
        'notes' => 'Ongewijzigde catalogusvermelding',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValueId,
        'stamp_catalog_entry_id' => $catalogEntryId,
        'condition_id' => $this->condition->id,
        'catalog_value' => '125.00',
        'currency' => 'EUR',
        'notes' => 'Ongewijzigde cataloguswaarde',
        'needs_review' => false,
    ]);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('meerdere verschillende wijzigingen voor meerdere postzegels worden correct geregistreerd binnen een geschiedenisbatch', function () {
    // Gebruik de bestaande testpostzegel voor het aanmaken van catalogusgegevens.
    $createdStamp = $this->stamp;

    // Maak een postzegel aan waarvan de catalogusgegevens worden gewijzigd.
    $updatedStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een postzegel aan waarvan de catalogusgegevens worden verwijderd.
    $deletedStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een postzegel aan waarvan de catalogusgegevens ongewijzigd blijven.
    $unchangedStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak de te wijzigen catalogusvermelding aan.
    $updatedEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-013-OLD',
        'notes' => 'Te wijzigen catalogusvermelding',
    ]);

    // Maak de te wijzigen cataloguswaarde aan.
    $updatedValue = $updatedEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '150.00',
        'currency' => 'EUR',
        'notes' => 'Te wijzigen cataloguswaarde',
        'needs_review' => false,
    ]);

    // Maak de te verwijderen catalogusvermelding aan.
    $deletedEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $deletedStamp->id,
        'catalog_number' => 'HISTORY-013-DELETE',
        'notes' => 'Te verwijderen catalogusvermelding',
    ]);

    // Maak de te verwijderen cataloguswaarde aan.
    $deletedValue = $deletedEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '75.00',
        'currency' => 'EUR',
        'notes' => 'Te verwijderen cataloguswaarde',
        'needs_review' => true,
    ]);

    // Maak de ongewijzigde catalogusvermelding aan.
    $unchangedEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $unchangedStamp->id,
        'catalog_number' => 'HISTORY-013-UNCHANGED',
        'notes' => 'Ongewijzigde catalogusvermelding',
    ]);

    // Maak de ongewijzigde cataloguswaarde aan.
    $unchangedValue = $unchangedEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '50.00',
        'currency' => 'EUR',
        'notes' => 'Ongewijzigde cataloguswaarde',
        'needs_review' => false,
    ]);

    // Haal alle relevante oorspronkelijke recordgegevens opnieuw op.
    $updatedEntry->refresh();
    $updatedValue->refresh();
    $deletedEntry->refresh();
    $deletedValue->refresh();
    $unchangedEntry->refresh();
    $unchangedValue->refresh();

    // Bewaar de oorspronkelijke attributen van de te verwijderen records.
    $originalDeletedEntryAttributes = $deletedEntry->getAttributes();
    $originalDeletedValueAttributes = $deletedValue->getAttributes();

    // Bewaar de oorspronkelijke attributen van de ongewijzigde records.
    $originalUnchangedEntryAttributes = $unchangedEntry->getAttributes();
    $originalUnchangedValueAttributes = $unchangedValue->getAttributes();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 4);
    $this->assertDatabaseCount('stamp_catalog_entries', 3);
    $this->assertDatabaseCount('stamp_catalog_values', 3);
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de eerste postzegel nog geen catalogusvermelding heeft.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $createdStamp->id,
    ]);

    // Controleer de oorspronkelijke te wijzigen catalogusgegevens.
    expect($updatedEntry->catalog_number)
        ->toBe('HISTORY-013-OLD');

    expect($updatedValue->catalog_value)->toBe('150.00');
    expect($updatedValue->needs_review)->toBeFalse();

    // Controleer de oorspronkelijke te verwijderen catalogusgegevens.
    expect($deletedEntry->catalog_number)
        ->toBe('HISTORY-013-DELETE');

    expect($deletedValue->catalog_value)->toBe('75.00');
    expect($deletedValue->needs_review)->toBeTrue();

    // Controleer de oorspronkelijke ongewijzigde catalogusgegevens.
    expect($unchangedEntry->catalog_number)
        ->toBe('HISTORY-013-UNCHANGED');

    expect($unchangedValue->catalog_value)->toBe('50.00');
    expect($unchangedValue->needs_review)->toBeFalse();

    // Verplaats de testtijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minute();

    // Voer alle vier de bewerkingen binnen dezelfde bulkbewerking uit.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $createdStamp->id => [
                        'catalog_number' => 'HISTORY-013-CREATED',
                        'values' => [
                            $this->condition->id => '200.00',
                        ],
                    ],
                    $updatedStamp->id => [
                        'catalog_number' => 'HISTORY-013-NEW',
                        'values' => [
                            $this->condition->id => '175.00',
                        ],
                    ],
                    $deletedStamp->id => [
                        'catalog_number' => '',
                        'values' => [
                            $this->condition->id => null,
                        ],
                    ],
                    $unchangedStamp->id => [
                        'catalog_number' => 'HISTORY-013-UNCHANGED',
                        'values' => [
                            $this->condition->id => '50.00',
                        ],
                    ],
                ],
            ]
        );

    // Herstel de oorspronkelijke testtijd.
    $this->travelBack();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer de uiteindelijke aantallen catalogusgegevens.
    $this->assertDatabaseCount('stamps', 4);
    $this->assertDatabaseCount('stamp_catalog_entries', 3);
    $this->assertDatabaseCount('stamp_catalog_values', 3);

    // Haal de nieuw aangemaakte catalogusvermelding op.
    $createdEntry = StampCatalogEntry::query()
        ->where('catalog_edition_id', $this->edition->id)
        ->where('stamp_id', $createdStamp->id)
        ->sole();

    // Haal de nieuw aangemaakte cataloguswaarde op.
    $createdValue = $createdEntry->values()
        ->where('condition_id', $this->condition->id)
        ->sole();

    // Controleer de nieuw aangemaakte catalogusgegevens.
    expect($createdEntry->catalog_number)
        ->toBe('HISTORY-013-CREATED');

    expect($createdValue->catalog_value)->toBe('200.00');
    expect($createdValue->currency)->toBe('EUR');
    expect($createdValue->needs_review)->toBeFalse();

    // Haal de gewijzigde catalogusgegevens opnieuw op.
    $updatedEntry->refresh();
    $updatedValue->refresh();

    // Controleer de gewijzigde catalogusgegevens.
    expect($updatedEntry->catalog_number)
        ->toBe('HISTORY-013-NEW');

    expect($updatedValue->catalog_value)->toBe('175.00');
    expect($updatedValue->currency)->toBe('EUR');
    expect($updatedValue->needs_review)->toBeTrue();

    // Controleer dat de oorspronkelijke notities behouden blijven.
    expect($updatedEntry->notes)
        ->toBe('Te wijzigen catalogusvermelding');

    expect($updatedValue->notes)
        ->toBe('Te wijzigen cataloguswaarde');

    // Controleer dat de te verwijderen catalogusvermelding niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $deletedEntry->id,
    ]);

    // Controleer dat de te verwijderen cataloguswaarde niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $deletedValue->id,
    ]);

    // Haal de ongewijzigde catalogusgegevens opnieuw op.
    $unchangedEntry->refresh();
    $unchangedValue->refresh();

    // Controleer dat alle oorspronkelijke attributen ongewijzigd blijven.
    expect($unchangedEntry->getAttributes())
        ->toBe($originalUnchangedEntryAttributes);

    expect($unchangedValue->getAttributes())
        ->toBe($originalUnchangedValueAttributes);

    // Controleer dat er precies één geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er precies zes geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 6);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de gegevens van de geschiedenisbatch.
    expect($batch->catalog_edition_id)->toBe($this->edition->id);
    expect($batch->user_id)->toBe($this->user->id);
    expect($batch->user_name)->toBe($this->user->name);
    expect($batch->catalog_name)->toBe($this->catalog->name);
    expect($batch->catalog_edition_name)->toBe($this->edition->edition_name);
    expect($batch->operation)->toBe('bulk_edit');

    // Haal alle geschiedenisregels in registratievolgorde op.
    $logs = $batch->logs()
        ->orderBy('id')
        ->get();

    // Controleer het totale aantal geschiedenisregels.
    expect($logs)->toHaveCount(6);

    // Controleer dat alle geschiedenisregels bij dezelfde batch horen.
    foreach ($logs as $log) {
        expect($log->catalog_change_batch_id)->toBe($batch->id);
        expect($log->catalog_edition_id)->toBe($this->edition->id);
        expect($log->stamp_series_id)->toBeNull();
        expect($log->batch->id)->toBe($batch->id);
        expect($log->catalogEdition->id)->toBe($this->edition->id);
    }

    // Controleer het aantal geschiedenisregels per actietype.
    expect($logs->where('action', 'created'))->toHaveCount(2);
    expect($logs->where('action', 'updated'))->toHaveCount(2);
    expect($logs->where('action', 'deleted'))->toHaveCount(2);

    // Controleer het aantal geschiedenisregels per recordtype.
    expect($logs->where('record_type', 'stamp_catalog_entry'))
        ->toHaveCount(3);

    expect($logs->where('record_type', 'stamp_catalog_value'))
        ->toHaveCount(3);

    // Haal de geschiedenisregel van de aangemaakte catalogusvermelding op.
    $createdEntryLog = $logs
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $createdEntry->id)
        ->where('action', 'created')
        ->sole();

    // Haal de geschiedenisregel van de aangemaakte cataloguswaarde op.
    $createdValueLog = $logs
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $createdValue->id)
        ->where('action', 'created')
        ->sole();

    // Controleer de aangemaakte catalogusvermelding.
    expect($createdEntryLog->stamp_id)->toBe($createdStamp->id);
    expect($createdEntryLog->condition_id)->toBeNull();
    expect($createdEntryLog->old_values)->toBeNull();
    expect($createdEntryLog->new_values)->toBeArray();

    expect($createdEntryLog->new_values['catalog_number'])
        ->toBe('HISTORY-013-CREATED');

    expect($createdEntryLog->new_values['id'])
        ->toBe($createdEntry->id);

    // Controleer de aangemaakte cataloguswaarde.
    expect($createdValueLog->stamp_id)->toBe($createdStamp->id);
    expect($createdValueLog->condition_id)->toBe($this->condition->id);
    expect($createdValueLog->old_values)->toBeNull();
    expect($createdValueLog->new_values)->toBeArray();

    expect((float) $createdValueLog->new_values['catalog_value'])
        ->toBe(200.0);

    expect($createdValueLog->new_values['currency'])->toBe('EUR');

    expect($createdValueLog->new_values['stamp_catalog_entry_id'])
        ->toBe($createdEntry->id);

    // Controleer dat de catalogusvermelding vóór de waarde is aangemaakt.
    expect($createdEntryLog->id)->toBeLessThan($createdValueLog->id);

    // Haal de geschiedenisregel van de gewijzigde catalogusvermelding op.
    $updatedEntryLog = $logs
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $updatedEntry->id)
        ->where('action', 'updated')
        ->sole();

    // Haal de geschiedenisregel van de gewijzigde cataloguswaarde op.
    $updatedValueLog = $logs
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $updatedValue->id)
        ->where('action', 'updated')
        ->sole();

    // Controleer de gewijzigde catalogusvermelding.
    expect($updatedEntryLog->stamp_id)->toBe($updatedStamp->id);
    expect($updatedEntryLog->condition_id)->toBeNull();

    expect($updatedEntryLog->old_values)->toBe([
        'catalog_number' => 'HISTORY-013-OLD',
    ]);

    expect($updatedEntryLog->new_values)->toBe([
        'catalog_number' => 'HISTORY-013-NEW',
    ]);

    // Controleer de gewijzigde cataloguswaarde.
    expect($updatedValueLog->stamp_id)->toBe($updatedStamp->id);
    expect($updatedValueLog->condition_id)->toBe($this->condition->id);

    expect($updatedValueLog->old_values)->toHaveCount(2);
    expect($updatedValueLog->new_values)->toHaveCount(2);

    expect((float) $updatedValueLog->old_values['catalog_value'])
        ->toBe(150.0);

    expect((float) $updatedValueLog->new_values['catalog_value'])
        ->toBe(175.0);

    // Controleer de automatische wijziging van de controlemarkering.
    expect($updatedValueLog->old_values['needs_review'])
        ->toBeIn([false, 0, '0']);

    expect($updatedValueLog->new_values['needs_review'])
        ->toBeIn([true, 1, '1']);

    // Controleer dat timestamps niet als gewijzigde velden zijn geregistreerd.
    expect($updatedValueLog->old_values)
        ->not->toHaveKey('updated_at');

    expect($updatedValueLog->new_values)
        ->not->toHaveKey('updated_at');

    // Haal de geschiedenisregel van de verwijderde catalogusvermelding op.
    $deletedEntryLog = $logs
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $deletedEntry->id)
        ->where('action', 'deleted')
        ->sole();

    // Haal de geschiedenisregel van de verwijderde cataloguswaarde op.
    $deletedValueLog = $logs
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $deletedValue->id)
        ->where('action', 'deleted')
        ->sole();

    // Controleer de verwijderde catalogusvermelding.
    expect($deletedEntryLog->stamp_id)->toBe($deletedStamp->id);
    expect($deletedEntryLog->condition_id)->toBeNull();
    expect($deletedEntryLog->new_values)->toBeNull();

    expect($deletedEntryLog->old_values)
        ->toHaveCount(count($originalDeletedEntryAttributes));

    foreach ($originalDeletedEntryAttributes as $attribute => $originalValue) {
        expect($deletedEntryLog->old_values)->toHaveKey($attribute);
        expect($deletedEntryLog->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer de verwijderde cataloguswaarde.
    expect($deletedValueLog->stamp_id)->toBe($deletedStamp->id);
    expect($deletedValueLog->condition_id)->toBe($this->condition->id);
    expect($deletedValueLog->new_values)->toBeNull();

    expect($deletedValueLog->old_values)
        ->toHaveCount(count($originalDeletedValueAttributes));

    foreach ($originalDeletedValueAttributes as $attribute => $originalValue) {
        expect($deletedValueLog->old_values)->toHaveKey($attribute);
        expect($deletedValueLog->old_values[$attribute])->toBe($originalValue);
    }

    // Controleer dat de cataloguswaarde vóór de vermelding is verwijderd.
    expect($deletedValueLog->id)->toBeLessThan($deletedEntryLog->id);

    // Controleer het aantal geschiedenisregels per gewijzigde postzegel.
    expect($logs->where('stamp_id', $createdStamp->id))
        ->toHaveCount(2);

    expect($logs->where('stamp_id', $updatedStamp->id))
        ->toHaveCount(2);

    expect($logs->where('stamp_id', $deletedStamp->id))
        ->toHaveCount(2);

    // Controleer dat de ongewijzigde postzegel geen geschiedenisregels heeft.
    expect($logs->where('stamp_id', $unchangedStamp->id))
        ->toHaveCount(0);

    $this->assertDatabaseMissing('catalog_change_logs', [
        'stamp_id' => $unchangedStamp->id,
    ]);

    // Controleer dat alle vier de oorspronkelijke postzegels behouden blijven.
    foreach ([
        $createdStamp,
        $updatedStamp,
        $deletedStamp,
        $unchangedStamp,
    ] as $stamp) {
        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'country_id' => $this->country->id,
            'issue_year' => 2025,
        ]);
    }

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
        'slug' => 'postfris',
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel behouden blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);
});

test('een bulkbewerking met ongeldige invoer veroorzaakt geen cataloguswijzigingen of geschiedenisregistratie', function () {
    // Gebruik de bestaande testpostzegel voor een voorgenomen wijziging.
    $updatedStamp = $this->stamp;

    // Maak een postzegel aan voor nieuwe catalogusgegevens.
    $createdStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een postzegel aan waarvoor ongeldige invoer wordt verzonden.
    $invalidStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-014-OLD',
        'notes' => 'Oorspronkelijke catalogusvermelding',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '140.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => false,
    ]);

    // Haal de oorspronkelijke gegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Bewaar alle oorspronkelijke attributen.
    $originalEntryAttributes = $catalogEntry->getAttributes();
    $originalValueAttributes = $catalogValue->getAttributes();

    // Bewaar de oorspronkelijke record-ID's.
    $catalogEntryId = $catalogEntry->id;
    $catalogValueId = $catalogValue->id;

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 3);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer de oorspronkelijke catalogusgegevens.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-014-OLD');

    expect($catalogValue->catalog_value)->toBe('140.00');
    expect($catalogValue->currency)->toBe('EUR');
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat de tweede postzegel nog geen catalogusvermelding heeft.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $createdStamp->id,
    ]);

    // Controleer dat de derde postzegel nog geen catalogusvermelding heeft.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $invalidStamp->id,
    ]);

    // Bepaal de URL van de bulkbewerkingspagina.
    $bulkEditUrl = route('backend.catalogs.editions.bulk-edit', [
        $this->catalog,
        $this->edition,
    ]);

    // Verplaats de testtijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minute();

    // Verzend twee geldige bewerkingen en één ongeldige bewerking.
    $response = $this
        ->actingAs($this->user)
        ->from($bulkEditUrl)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $updatedStamp->id => [
                        'catalog_number' => 'HISTORY-014-NEW',
                        'values' => [
                            $this->condition->id => '165.00',
                        ],
                    ],
                    $createdStamp->id => [
                        'catalog_number' => 'HISTORY-014-CREATED',
                        'values' => [
                            $this->condition->id => '85.00',
                        ],
                    ],
                    $invalidStamp->id => [
                        'catalog_number' => '',
                        'values' => [
                            $this->condition->id => '35.00',
                        ],
                    ],
                ],
            ]
        );

    // Herstel de oorspronkelijke testtijd.
    $this->travelBack();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect($bulkEditUrl);

    // Controleer de verwachte validatiefout.
    $response->assertSessionHasErrors([
        "stamps.{$invalidStamp->id}.catalog_number",
    ]);

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Haal de oorspronkelijke catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat de oorspronkelijke record-ID's behouden blijven.
    expect($catalogEntry->id)->toBe($catalogEntryId);
    expect($catalogValue->id)->toBe($catalogValueId);

    // Controleer dat het oorspronkelijke catalogusnummer behouden blijft.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-014-OLD');

    // Controleer dat de oorspronkelijke cataloguswaarde behouden blijft.
    expect($catalogValue->catalog_value)->toBe('140.00');

    // Controleer dat de oorspronkelijke valuta behouden blijft.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de oorspronkelijke controlemarkering behouden blijft.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat de oorspronkelijke notities behouden blijven.
    expect($catalogEntry->notes)
        ->toBe('Oorspronkelijke catalogusvermelding');

    expect($catalogValue->notes)
        ->toBe('Oorspronkelijke cataloguswaarde');

    // Controleer dat alle oorspronkelijke attributen ongewijzigd blijven.
    expect($catalogEntry->getAttributes())
        ->toBe($originalEntryAttributes);

    expect($catalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer expliciet dat de wijzigingsdatums behouden blijven.
    expect($catalogEntry->getRawOriginal('updated_at'))
        ->toBe($originalEntryAttributes['updated_at']);

    expect($catalogValue->getRawOriginal('updated_at'))
        ->toBe($originalValueAttributes['updated_at']);

    // Controleer dat er geen nieuwe catalogusvermeldingen zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat er geen nieuwe cataloguswaarden zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat het voorgenomen nieuwe catalogusnummer niet bestaat.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-014-NEW',
    ]);

    // Controleer dat de voorgenomen gewijzigde cataloguswaarde niet bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValueId,
        'catalog_value' => '165.00',
    ]);

    // Controleer dat de geldige nieuwe catalogusvermelding niet is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $createdStamp->id,
    ]);

    // Controleer dat de ongeldige catalogusvermelding niet is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $invalidStamp->id,
    ]);

    // Controleer dat de voorgenomen nieuwe cataloguswaarden niet bestaan.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'catalog_value' => '85.00',
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'catalog_value' => '35.00',
    ]);

    // Controleer dat er geen geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat er geen geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat er geen geschiedenisbatch voor deze gebruiker bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'user_id' => $this->user->id,
    ]);

    // Controleer dat er geen geschiedenisbatch voor deze editie bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'catalog_edition_id' => $this->edition->id,
    ]);

    // Controleer dat geen van de drie postzegels geschiedenisregels heeft.
    foreach ([
        $updatedStamp,
        $createdStamp,
        $invalidStamp,
    ] as $stamp) {
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $stamp->id,
        ]);
    }

    // Controleer dat er geen onterechte aanmaakregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'created',
    ]);

    // Controleer dat er geen onterechte wijzigingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Controleer dat er geen onterechte verwijderingsregistratie bestaat.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'deleted',
    ]);

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntryId,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-014-OLD',
        'notes' => 'Oorspronkelijke catalogusvermelding',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValueId,
        'stamp_catalog_entry_id' => $catalogEntryId,
        'condition_id' => $this->condition->id,
        'catalog_value' => '140.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => false,
    ]);

    // Controleer dat alle drie de postzegels behouden blijven.
    $this->assertDatabaseCount('stamps', 3);

    foreach ([
        $updatedStamp,
        $createdStamp,
        $invalidStamp,
    ] as $stamp) {
        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'country_id' => $this->country->id,
            'issue_year' => 2025,
        ]);
    }

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
        'slug' => 'postfris',
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel behouden blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);
});

test('catalogusgegevens en geschiedenisregistraties worden volledig teruggedraaid bij een fout tijdens een bulkbewerking', function () {
    // Gebruik de bestaande testpostzegel voor een voorgenomen wijziging.
    $updatedStamp = $this->stamp;

    // Maak een tweede postzegel aan voor nieuwe catalogusgegevens.
    $createdStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-015-OLD',
        'notes' => 'Oorspronkelijke catalogusvermelding rollbacktest',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '150.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde rollbacktest',
        'needs_review' => false,
    ]);

    // Haal alle oorspronkelijke attributen opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Bewaar alle oorspronkelijke attributen.
    $originalEntryAttributes = $catalogEntry->getAttributes();
    $originalValueAttributes = $catalogValue->getAttributes();

    // Bewaar de oorspronkelijke record-ID's.
    $catalogEntryId = $catalogEntry->id;
    $catalogValueId = $catalogValue->id;

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 2);
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer de oorspronkelijke catalogusgegevens.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-015-OLD');

    expect($catalogValue->catalog_value)->toBe('150.00');
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat de tweede postzegel nog geen catalogusvermelding heeft.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $createdStamp->id,
    ]);

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de eventdispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Gebruik de tijdelijke dispatcher voor de gesimuleerde fout.
    StampCatalogValue::setEventDispatcher($temporaryDispatcher);

    // Houd bij of de gesimuleerde fout daadwerkelijk wordt bereikt.
    $simulatedFailureReached = false;

    // Bewaar de tijdelijke databasegegevens vlak vóór de fout.
    $stateBeforeFailure = [];

    // Simuleer een fout tijdens het aanmaken van de nieuwe cataloguswaarde.
    StampCatalogValue::creating(function (StampCatalogValue $value) use (
        $createdStamp,
        $catalogEntryId,
        $catalogValueId,
        &$simulatedFailureReached,
        &$stateBeforeFailure
    ) {
        // Haal de bijbehorende catalogusvermelding op.
        $entry = $value->catalogEntry;

        // Simuleer uitsluitend een fout voor de tweede postzegel.
        if ($entry?->stamp_id !== $createdStamp->id) {
            return;
        }

        // Leg vast dat het bedoelde foutmoment is bereikt.
        $simulatedFailureReached = true;

        // Haal de actieve geschiedenisbatch op.
        $history = app(CatalogChangeHistoryService::class);
        $activeBatch = $history->getActiveBatch();

        // Leg de tijdelijke databasegegevens vast.
        $stateBeforeFailure = [
            'active_batch_id' => $activeBatch?->id,
            'batch_count' => CatalogChangeBatch::count(),
            'log_count' => CatalogChangeLog::count(),
            'entry_count' => StampCatalogEntry::count(),
            'value_count' => StampCatalogValue::count(),
            'updated_catalog_number' => StampCatalogEntry::findOrFail(
                $catalogEntryId
            )->catalog_number,
            'updated_catalog_value' => StampCatalogValue::findOrFail(
                $catalogValueId
            )->catalog_value,
            'updated_needs_review' => StampCatalogValue::findOrFail(
                $catalogValueId
            )->needs_review,
            'created_entry_id' => $entry->id,
            'created_catalog_number' => $entry->catalog_number,
            'created_log_count' => CatalogChangeLog::query()
                ->where('action', 'created')
                ->count(),
            'updated_log_count' => CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count(),
            'deleted_log_count' => CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count(),
            'active_batch_log_count' => $activeBatch?->logs()->count(),
        ];

        // Onderbreek bewust de lopende databasetransactie.
        throw new RuntimeException(
            'Gesimuleerde fout tijdens het aanmaken van een cataloguswaarde.'
        );
    });

    // Zorg dat de gesimuleerde uitzondering rechtstreeks beschikbaar komt.
    $this->withoutExceptionHandling();

    // Verplaats de testtijd om eventuele timestampwijzigingen te detecteren.
    $this->travel(1)->minute();

    try {
        // Voer een volledig geldige bulkbewerking uit.
        expect(fn () => $this
            ->actingAs($this->user)
            ->patch(
                route('backend.catalogs.editions.bulk-update', [
                    $this->catalog,
                    $this->edition,
                ]),
                [
                    'stamps' => [
                        $updatedStamp->id => [
                            'catalog_number' => 'HISTORY-015-NEW',
                            'values' => [
                                $this->condition->id => '175.00',
                            ],
                        ],
                        $createdStamp->id => [
                            'catalog_number' => 'HISTORY-015-CREATED',
                            'values' => [
                                $this->condition->id => '95.00',
                            ],
                        ],
                    ],
                ]
            ))
            ->toThrow(
                RuntimeException::class,
                'Gesimuleerde fout tijdens het aanmaken van een cataloguswaarde.'
            );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel de oorspronkelijke exception handling.
        $this->withExceptionHandling();

        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer dat de bedoelde gesimuleerde fout daadwerkelijk is bereikt.
    expect($simulatedFailureReached)->toBeTrue();

    // Controleer dat de tijdelijke databasegegevens zijn vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer dat er vóór de fout een actieve geschiedenisbatch bestond.
    expect($stateBeforeFailure['active_batch_id'])->not->toBeNull();

    // Controleer dat er vóór de fout één geschiedenisbatch bestond.
    expect($stateBeforeFailure['batch_count'])->toBe(1);

    // Controleer dat er vóór de fout drie geschiedenisregels bestonden.
    expect($stateBeforeFailure['log_count'])->toBe(3);

    // Controleer dat alle drie de regels bij de actieve batch hoorden.
    expect($stateBeforeFailure['active_batch_log_count'])->toBe(3);

    // Controleer de tijdelijke aantallen catalogusgegevens.
    expect($stateBeforeFailure['entry_count'])->toBe(2);
    expect($stateBeforeFailure['value_count'])->toBe(1);

    // Controleer dat het eerste catalogusnummer daadwerkelijk was gewijzigd.
    expect($stateBeforeFailure['updated_catalog_number'])
        ->toBe('HISTORY-015-NEW');

    // Controleer dat de eerste cataloguswaarde daadwerkelijk was gewijzigd.
    expect($stateBeforeFailure['updated_catalog_value'])
        ->toBe('175.00');

    // Controleer dat de controlemarkering tijdelijk was geactiveerd.
    expect($stateBeforeFailure['updated_needs_review'])->toBeTrue();

    // Controleer dat de tweede catalogusvermelding daadwerkelijk was aangemaakt.
    expect($stateBeforeFailure['created_entry_id'])->not->toBeNull();

    expect($stateBeforeFailure['created_catalog_number'])
        ->toBe('HISTORY-015-CREATED');

    // Controleer de tijdelijke verdeling van de geschiedenisregels.
    expect($stateBeforeFailure['created_log_count'])->toBe(1);
    expect($stateBeforeFailure['updated_log_count'])->toBe(2);
    expect($stateBeforeFailure['deleted_log_count'])->toBe(0);

    // Haal de oorspronkelijke catalogusgegevens opnieuw op na de rollback.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat de oorspronkelijke record-ID's behouden blijven.
    expect($catalogEntry->id)->toBe($catalogEntryId);
    expect($catalogValue->id)->toBe($catalogValueId);

    // Controleer dat het oorspronkelijke catalogusnummer is hersteld.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-015-OLD');

    // Controleer dat de oorspronkelijke cataloguswaarde is hersteld.
    expect($catalogValue->catalog_value)->toBe('150.00');

    // Controleer dat de oorspronkelijke valuta behouden blijft.
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de oorspronkelijke controlemarkering is hersteld.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat de oorspronkelijke notities behouden blijven.
    expect($catalogEntry->notes)
        ->toBe('Oorspronkelijke catalogusvermelding rollbacktest');

    expect($catalogValue->notes)
        ->toBe('Oorspronkelijke cataloguswaarde rollbacktest');

    // Controleer dat alle oorspronkelijke attributen zijn hersteld.
    expect($catalogEntry->getAttributes())
        ->toBe($originalEntryAttributes);

    expect($catalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer expliciet dat de oorspronkelijke wijzigingsdatums zijn hersteld.
    expect($catalogEntry->getRawOriginal('updated_at'))
        ->toBe($originalEntryAttributes['updated_at']);

    expect($catalogValue->getRawOriginal('updated_at'))
        ->toBe($originalValueAttributes['updated_at']);

    // Controleer dat de tijdelijke catalogusvermelding is teruggedraaid.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $stateBeforeFailure['created_entry_id'],
    ]);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $createdStamp->id,
    ]);

    // Controleer dat het tijdelijk gewijzigde catalogusnummer niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_number' => 'HISTORY-015-NEW',
    ]);

    // Controleer dat de tijdelijk gewijzigde cataloguswaarde niet meer bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValueId,
        'catalog_value' => '175.00',
    ]);

    // Controleer dat de nieuwe cataloguswaarde niet bestaat.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'catalog_value' => '95.00',
    ]);

    // Controleer dat de oorspronkelijke aantallen zijn hersteld.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de geschiedenisbatch volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat alle geschiedenisregels volledig zijn teruggedraaid.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de tijdelijke geschiedenisbatch niet meer bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $stateBeforeFailure['active_batch_id'],
    ]);

    // Controleer dat er geen geschiedenisregels van de tijdelijke batch bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_change_batch_id' => $stateBeforeFailure['active_batch_id'],
    ]);

    // Controleer dat er geen geschiedenisregels voor beide postzegels bestaan.
    foreach ([$updatedStamp, $createdStamp] as $stamp) {
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $stamp->id,
        ]);
    }

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntryId,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $updatedStamp->id,
        'catalog_number' => 'HISTORY-015-OLD',
        'notes' => 'Oorspronkelijke catalogusvermelding rollbacktest',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValueId,
        'stamp_catalog_entry_id' => $catalogEntryId,
        'condition_id' => $this->condition->id,
        'catalog_value' => '150.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde rollbacktest',
        'needs_review' => false,
    ]);

    // Controleer dat beide oorspronkelijke postzegels behouden blijven.
    $this->assertDatabaseCount('stamps', 2);

    foreach ([$updatedStamp, $createdStamp] as $stamp) {
        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
            'country_id' => $this->country->id,
            'issue_year' => 2025,
        ]);
    }

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
        'name' => $this->catalog->name,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
        'slug' => 'postfris',
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel behouden blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);

    // Controleer dat de oorspronkelijke eventdispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);
});

test('de actieve geschiedenisbatch wordt na een succesvolle bulkbewerking correct opgeruimd', function () {
    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer dat er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Controleer de oorspronkelijke databasegegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de eventdispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Activeer de tijdelijke eventdispatcher.
    StampCatalogValue::setEventDispatcher($temporaryDispatcher);

    // Bewaar de actieve geschiedenisbatch tijdens de bulkbewerking.
    /** @var CatalogChangeBatch|null $activeBatchDuringOperation */
    $activeBatchDuringOperation = null;

    // Bewaar de gebruikte geschiedenisservice tijdens de bulkbewerking.
    /** @var CatalogChangeHistoryService|null $historyDuringOperation */
    $historyDuringOperation = null;

    // Houd bij hoe vaak de tijdelijke eventlistener wordt uitgevoerd.
    $listenerCallCount = 0;

    // Controleer de actieve geschiedenisbatch tijdens het aanmaken van een waarde.
    StampCatalogValue::creating(function (StampCatalogValue $value) use (
        &$activeBatchDuringOperation,
        &$historyDuringOperation,
        &$listenerCallCount
    ) {
        // Registreer dat de tijdelijke eventlistener is uitgevoerd.
        $listenerCallCount++;

        // Haal de geschiedenisservice op tijdens de bulkbewerking.
        $historyDuringOperation = app(CatalogChangeHistoryService::class);

        // Bewaar de actieve geschiedenisbatch.
        $activeBatchDuringOperation = $historyDuringOperation->getActiveBatch();
    });

    try {
        // Voer een succesvolle bulkbewerking uit.
        $response = $this
            ->actingAs($this->user)
            ->patch(
                route('backend.catalogs.editions.bulk-update', [
                    $this->catalog,
                    $this->edition,
                ]),
                [
                    'stamps' => [
                        $this->stamp->id => [
                            'catalog_number' => 'HISTORY-016',
                            'values' => [
                                $this->condition->id => '160.00',
                            ],
                        ],
                    ],
                ]
            );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);
    }

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de tijdelijke eventlistener precies één keer is uitgevoerd.
    expect($listenerCallCount)->toBe(1);

    // Controleer dat de geschiedenisservice tijdens de bewerking beschikbaar was.
    expect($historyDuringOperation)
        ->toBeInstanceOf(CatalogChangeHistoryService::class);

    // Controleer dat dezelfde geschiedenisservice is gebruikt.
    expect($historyDuringOperation)->toBe($history);

    // Controleer dat tijdens de bulkbewerking een actieve batch bestond.
    expect($activeBatchDuringOperation)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    // Controleer de gegevens van de tijdens de bewerking actieve batch.
    expect($activeBatchDuringOperation->catalog_edition_id)
        ->toBe($this->edition->id);

    expect($activeBatchDuringOperation->user_id)
        ->toBe($this->user->id);

    expect($activeBatchDuringOperation->operation)
        ->toBe('bulk_edit');

    // Controleer dat de actieve batch na afloop is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    // Controleer dit ook via de tijdens de bewerking gebruikte service.
    expect($historyDuringOperation->getActiveBatch())->toBeNull();

    // Controleer dit ook via de servicecontainer.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Controleer dat de catalogusgegevens correct zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de geschiedenisgegevens behouden blijven.
    $this->assertDatabaseCount('catalog_change_batches', 1);
    $this->assertDatabaseCount('catalog_change_logs', 2);

    // Haal de opgeslagen geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer dat dit dezelfde batch is die tijdens de bewerking actief was.
    expect($batch->id)->toBe($activeBatchDuringOperation->id);

    // Controleer het aantal bijbehorende geschiedenisregels.
    expect($batch->logs()->count())->toBe(2);

    // Controleer dat beide geschiedenisregels aanmaakregistraties zijn.
    expect($batch->logs()->where('action', 'created')->count())
        ->toBe(2);

    // Haal de opgeslagen catalogusvermelding op.
    $catalogEntry = StampCatalogEntry::query()
        ->where('catalog_edition_id', $this->edition->id)
        ->where('stamp_id', $this->stamp->id)
        ->sole();

    // Haal de opgeslagen cataloguswaarde op.
    $catalogValue = $catalogEntry->values()
        ->where('condition_id', $this->condition->id)
        ->sole();

    // Controleer de opgeslagen catalogusgegevens.
    expect($catalogEntry->catalog_number)->toBe('HISTORY-016');
    expect($catalogValue->catalog_value)->toBe('160.00');
    expect($catalogValue->currency)->toBe('EUR');
    expect($catalogValue->needs_review)->toBeFalse();

    // Bewaar de oorspronkelijke geschiedenisgegevens.
    $originalBatchAttributes = $batch->getAttributes();

    $originalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Controleer dat de oorspronkelijke eventdispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Voer bewust een modelbewerking uit zonder actieve geschiedenisbatch.
    $catalogEntry->update([
        'catalog_number' => 'HISTORY-016-AFTER',
    ]);

    // Wijzig ook de cataloguswaarde zonder actieve geschiedenisbatch.
    $catalogValue->update([
        'catalog_value' => '185.00',
    ]);

    // Haal de gewijzigde catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat de latere modelbewerkingen daadwerkelijk zijn uitgevoerd.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-016-AFTER');

    expect($catalogValue->catalog_value)->toBe('185.00');
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat er nog steeds geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Controleer dat er geen extra geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    // Controleer dat er geen extra geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 2);

    // Controleer dat er geen onterechte wijzigingsregistraties bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'action' => 'updated',
    ]);

    // Haal de oorspronkelijke geschiedenisbatch opnieuw op.
    $batch->refresh();

    // Controleer dat de oorspronkelijke geschiedenisbatch ongewijzigd blijft.
    expect($batch->getAttributes())
        ->toBe($originalBatchAttributes);

    // Haal de uiteindelijke geschiedenisregels opnieuw op.
    $finalLogs = $batch->logs()
        ->orderBy('id')
        ->get()
        ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
        ->all();

    // Controleer dat alle oorspronkelijke geschiedenisregels ongewijzigd blijven.
    expect($finalLogs)->toBe($originalLogs);

    // Controleer de oorspronkelijke aanmaakregistratie van de catalogusvermelding.
    $entryLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $catalogEntry->id)
        ->sole();

    expect($entryLog->action)->toBe('created');
    expect($entryLog->old_values)->toBeNull();

    expect($entryLog->new_values['catalog_number'])
        ->toBe('HISTORY-016');

    // Controleer de oorspronkelijke aanmaakregistratie van de cataloguswaarde.
    $valueLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $catalogValue->id)
        ->sole();

    expect($valueLog->action)->toBe('created');
    expect($valueLog->old_values)->toBeNull();

    expect((float) $valueLog->new_values['catalog_value'])
        ->toBe(160.0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('de actieve geschiedenisbatch wordt ook na een fout en rollback correct opgeruimd', function () {
    // Haal de geschiedenisservice op.
    $history = app(CatalogChangeHistoryService::class);

    // Controleer dat er vooraf geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-017-OLD',
        'notes' => 'Oorspronkelijke catalogusvermelding',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => '170.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => false,
    ]);

    // Haal de oorspronkelijke catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Bewaar alle oorspronkelijke attributen.
    $originalEntryAttributes = $catalogEntry->getAttributes();
    $originalValueAttributes = $catalogValue->getAttributes();

    // Controleer de oorspronkelijke databasegegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Maak een tijdelijke kopie van de eventdispatcher.
    $temporaryDispatcher = clone $originalDispatcher;

    // Activeer de tijdelijke eventdispatcher.
    StampCatalogValue::setEventDispatcher($temporaryDispatcher);

    // Bewaar de geschiedenisservice tijdens de gesimuleerde fout.
    /** @var CatalogChangeHistoryService|null $historyDuringFailure */
    $historyDuringFailure = null;

    // Bewaar de actieve geschiedenisbatch tijdens de gesimuleerde fout.
    /** @var CatalogChangeBatch|null $activeBatchDuringFailure */
    $activeBatchDuringFailure = null;

    // Houd bij hoe vaak de tijdelijke eventlistener wordt uitgevoerd.
    $listenerCallCount = 0;

    // Bewaar de tijdelijke databasegegevens vóór de fout.
    $stateBeforeFailure = [];

    // Simuleer een fout tijdens het wijzigen van de cataloguswaarde.
    StampCatalogValue::updating(function (StampCatalogValue $value) use (
        $catalogValue,
        $catalogEntry,
        &$historyDuringFailure,
        &$activeBatchDuringFailure,
        &$listenerCallCount,
        &$stateBeforeFailure
    ) {
        // Simuleer uitsluitend een fout voor de bedoelde cataloguswaarde.
        if ($value->id !== $catalogValue->id) {
            return;
        }

        // Registreer dat de tijdelijke eventlistener is uitgevoerd.
        $listenerCallCount++;

        // Haal de geschiedenisservice tijdens de bewerking op.
        $historyDuringFailure = app(CatalogChangeHistoryService::class);

        // Bewaar de op dat moment actieve geschiedenisbatch.
        $activeBatchDuringFailure = $historyDuringFailure->getActiveBatch();

        // Leg de tijdelijke databasegegevens vast.
        $stateBeforeFailure = [
            'batch_count' => CatalogChangeBatch::count(),
            'log_count' => CatalogChangeLog::count(),
            'active_batch_log_count' => $activeBatchDuringFailure?->logs()->count(),
            'catalog_number' => StampCatalogEntry::findOrFail(
                $catalogEntry->id
            )->catalog_number,
            'stored_catalog_value' => StampCatalogValue::findOrFail(
                $catalogValue->id
            )->catalog_value,
            'pending_catalog_value' => $value->catalog_value,
        ];

        // Onderbreek bewust de lopende databasetransactie.
        throw new RuntimeException(
            'Gesimuleerde fout voor het opruimen van de actieve geschiedenisbatch.'
        );
    });

    // Zorg dat de gesimuleerde uitzondering rechtstreeks beschikbaar komt.
    $this->withoutExceptionHandling();

    // Verplaats de testtijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minute();

    try {
        // Voer een geldige bulkbewerking uit.
        expect(fn () => $this
            ->actingAs($this->user)
            ->patch(
                route('backend.catalogs.editions.bulk-update', [
                    $this->catalog,
                    $this->edition,
                ]),
                [
                    'stamps' => [
                        $this->stamp->id => [
                            'catalog_number' => 'HISTORY-017-NEW',
                            'values' => [
                                $this->condition->id => '195.00',
                            ],
                        ],
                    ],
                ]
            ))
            ->toThrow(
                RuntimeException::class,
                'Gesimuleerde fout voor het opruimen van de actieve geschiedenisbatch.'
            );
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel de oorspronkelijke exception handling.
        $this->withExceptionHandling();

        // Herstel de oorspronkelijke testtijd.
        $this->travelBack();
    }

    // Controleer dat de tijdelijke eventlistener precies één keer is uitgevoerd.
    expect($listenerCallCount)->toBe(1);

    // Controleer dat de geschiedenisservice tijdens de fout beschikbaar was.
    expect($historyDuringFailure)
        ->toBeInstanceOf(CatalogChangeHistoryService::class);

    // Controleer dat dezelfde geschiedenisservice is gebruikt.
    expect($historyDuringFailure)->toBe($history);

    // Controleer dat tijdens de fout een actieve geschiedenisbatch bestond.
    expect($activeBatchDuringFailure)
        ->toBeInstanceOf(CatalogChangeBatch::class);

    // Controleer de gegevens van de tijdelijk actieve geschiedenisbatch.
    expect($activeBatchDuringFailure->catalog_edition_id)
        ->toBe($this->edition->id);

    expect($activeBatchDuringFailure->user_id)
        ->toBe($this->user->id);

    expect($activeBatchDuringFailure->operation)
        ->toBe('bulk_edit');

    // Bewaar het ID van de teruggedraaide geschiedenisbatch.
    $rolledBackBatchId = $activeBatchDuringFailure->id;

    // Controleer dat de tijdelijke databasegegevens zijn vastgelegd.
    expect($stateBeforeFailure)->not->toBeEmpty();

    // Controleer dat vóór de fout één geschiedenisbatch bestond.
    expect($stateBeforeFailure['batch_count'])->toBe(1);

    // Controleer dat vóór de fout één geschiedenisregel bestond.
    expect($stateBeforeFailure['log_count'])->toBe(1);

    // Controleer dat deze geschiedenisregel bij de actieve batch hoorde.
    expect($stateBeforeFailure['active_batch_log_count'])->toBe(1);

    // Controleer dat het catalogusnummer daadwerkelijk was gewijzigd.
    expect($stateBeforeFailure['catalog_number'])
        ->toBe('HISTORY-017-NEW');

    // Controleer dat de cataloguswaarde nog niet was opgeslagen.
    expect($stateBeforeFailure['stored_catalog_value'])
        ->toBe('170.00');

    // Controleer de voorgenomen nieuwe cataloguswaarde.
    expect($stateBeforeFailure['pending_catalog_value'])
        ->toBe('195.00');

    // Controleer dat de actieve geschiedenisbatch na de fout is opgeruimd.
    expect($history->getActiveBatch())->toBeNull();

    // Controleer dit ook via de tijdens de fout gebruikte service.
    expect($historyDuringFailure->getActiveBatch())->toBeNull();

    // Controleer dit ook via de servicecontainer.
    expect(app(CatalogChangeHistoryService::class)->getActiveBatch())
        ->toBeNull();

    // Haal de oorspronkelijke catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat alle oorspronkelijke attributen zijn hersteld.
    expect($catalogEntry->getAttributes())
        ->toBe($originalEntryAttributes);

    expect($catalogValue->getAttributes())
        ->toBe($originalValueAttributes);

    // Controleer het oorspronkelijke catalogusnummer.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-017-OLD');

    // Controleer de oorspronkelijke cataloguswaarde.
    expect($catalogValue->catalog_value)->toBe('170.00');

    // Controleer de oorspronkelijke controlemarkering.
    expect($catalogValue->needs_review)->toBeFalse();

    // Controleer dat de geschiedenisbatch volledig is teruggedraaid.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat de geschiedenisregels volledig zijn teruggedraaid.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de tijdelijke geschiedenisbatch niet meer bestaat.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $rolledBackBatchId,
    ]);

    // Controleer dat er geen geschiedenisregels van deze batch bestaan.
    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_change_batch_id' => $rolledBackBatchId,
    ]);

    // Controleer dat de oorspronkelijke eventdispatcher is hersteld.
    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    // Voer bewust een modelbewerking uit zonder actieve geschiedenisbatch.
    $catalogEntry->update([
        'catalog_number' => 'HISTORY-017-AFTER',
    ]);

    // Wijzig ook de cataloguswaarde zonder actieve geschiedenisbatch.
    $catalogValue->update([
        'catalog_value' => '215.00',
    ]);

    // Haal de gewijzigde catalogusgegevens opnieuw op.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat de latere modelbewerkingen daadwerkelijk zijn uitgevoerd.
    expect($catalogEntry->catalog_number)
        ->toBe('HISTORY-017-AFTER');

    expect($catalogValue->catalog_value)->toBe('215.00');
    expect($catalogValue->currency)->toBe('EUR');
    expect($catalogValue->needs_review)->toBeTrue();

    // Controleer dat de oorspronkelijke notities behouden blijven.
    expect($catalogEntry->notes)
        ->toBe('Oorspronkelijke catalogusvermelding');

    expect($catalogValue->notes)
        ->toBe('Oorspronkelijke cataloguswaarde');

    // Controleer dat de tijdelijke eventlistener niet opnieuw is uitgevoerd.
    expect($listenerCallCount)->toBe(1);

    // Controleer dat er nog steeds geen actieve geschiedenisbatch bestaat.
    expect($history->getActiveBatch())->toBeNull();

    // Controleer dat er geen nieuwe geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat er geen ongewenste geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de teruggedraaide geschiedenisbatch niet is hergebruikt.
    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $rolledBackBatchId,
    ]);

    $this->assertDatabaseMissing('catalog_change_logs', [
        'catalog_change_batch_id' => $rolledBackBatchId,
    ]);

    // Controleer de uiteindelijke aantallen catalogusgegevens.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer de uiteindelijke catalogusvermelding.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'HISTORY-017-AFTER',
        'notes' => 'Oorspronkelijke catalogusvermelding',
    ]);

    // Controleer de uiteindelijke cataloguswaarde.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '215.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => true,
    ]);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});
