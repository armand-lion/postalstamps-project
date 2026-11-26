<?php

use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;

beforeEach(function () {
    // Gebruik Nederlands als standaardtaal tijdens de tests.
    app()->setLocale('nl');

    // Maak een gewone gebruiker zonder beheerdersrechten aan.
    $this->user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testland aan.
    $this->country = Country::create([
        'name' => 'Testland Bulkbewerking',
        'iso_code' => 'TB',
        'entity_type' => 'country',
        'slug' => 'testland-bulkbewerking',
        'is_active' => true,
    ]);

    // Maak een testcatalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Bulkbewerking',
        'abbreviation' => 'TB',
    ]);

    // Koppel het testland aan de catalogus.
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

    // Maak een postzegel aan die binnen de waarderingsregel valt.
    $this->stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);
});

test('een niet-ingelogde bezoeker wordt doorgestuurd naar de inlogpagina bij bulkbewerking', function () {
    // Open de bulkbewerkingspagina zonder ingelogde gebruiker.
    $response = $this->get(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer of de bezoeker wordt doorgestuurd naar de inlogpagina.
    $response->assertRedirect(route('login'));
});

test('een ingelogde gewone gebruiker kan de bulkbewerkingspagina openen', function () {
    // Open de bulkbewerkingspagina als ingelogde gewone gebruiker.
    $response = $this
        ->actingAs($this->user)
        ->get(route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ]));

    // Controleer of de bulkbewerkingspagina succesvol wordt weergegeven.
    $response->assertOk();
});

test('een ingelogde beheerder kan de bulkbewerkingspagina openen', function () {
    // Maak een gebruiker met beheerdersrechten aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Open de bulkbewerkingspagina als ingelogde beheerder.
    $response = $this
        ->actingAs($admin)
        ->get(route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ]));

    // Controleer of de bulkbewerkingspagina succesvol wordt weergegeven.
    $response->assertOk();
});

test('een niet-ingelogde bezoeker kan geen bulkbewerking opslaan', function () {
    // Probeer zonder ingelogde gebruiker een bulkbewerking op te slaan.
    $response = $this->patch(
        route('backend.catalogs.editions.bulk-update', [
            $this->catalog,
            $this->edition,
        ]),
        [
            'stamps' => [
                $this->stamp->id => [
                    'catalog_number' => 'TEST-001',
                    'values' => [
                        $this->condition->id => '12.50',
                    ],
                ],
            ],
        ]
    );

    // Controleer of de bezoeker wordt doorgestuurd naar de inlogpagina.
    $response->assertRedirect(route('login'));

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen cataloguswaarden zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisbatch is aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    // Controleer dat er geen geschiedenisregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_change_logs', 0);
});

test('een ingelogde gewone gebruiker kan via een bulkbewerking een catalogusnummer en cataloguswaarde aanmaken', function () {
    // Sla een catalogusnummer en cataloguswaarde op als gewone gebruiker.
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
                        'catalog_number' => 'TEST-001',
                        'values' => [
                            $this->condition->id => '12.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding is aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-001',
    ]);

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = $this->stamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer dat de cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    // Controleer dat beide records slechts eenmaal zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een catalogusnummer kan zonder cataloguswaarde worden aangemaakt via een bulkbewerking', function () {
    // Sla uitsluitend een catalogusnummer op.
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
                        'catalog_number' => 'TEST-002',
                        'values' => [
                            $this->condition->id => null,
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding correct is aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-002',
    ]);

    // Controleer dat er precies één catalogusvermelding bestaat.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    // Controleer dat er geen cataloguswaarden zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_values', 0);
});

test('een cataloguswaarde van nul wordt correct opgeslagen via een bulkbewerking', function () {
    // Sla een catalogusnummer met een cataloguswaarde van nul op.
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
                        'catalog_number' => 'TEST-003',
                        'values' => [
                            $this->condition->id => '0.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding correct is aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-003',
    ]);

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = $this->stamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer dat de cataloguswaarde van nul is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    // Haal de opgeslagen cataloguswaarde op.
    $catalogValue = $catalogEntry->values()
        ->where('condition_id', $this->condition->id)
        ->firstOrFail();

    // Controleer dat de waarde niet leeg is en twee decimalen bevat.
    expect($catalogValue->catalog_value)
        ->not->toBeNull()
        ->toBe('0.00');

    // Controleer dat beide records slechts eenmaal zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('meerdere catalogusvermeldingen en cataloguswaarden worden aangemaakt binnen een bulkbewerking', function () {
    // Maak een tweede postzegel binnen hetzelfde land aan.
    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    // Sla de catalogusgegevens van beide postzegels tegelijkertijd op.
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
                        'catalog_number' => 'TEST-BULK-001',
                        'values' => [
                            $this->condition->id => '12.50',
                        ],
                    ],
                    $secondStamp->id => [
                        'catalog_number' => 'TEST-BULK-002',
                        'values' => [
                            $this->condition->id => '25.75',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer de catalogusvermelding van de eerste postzegel.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-BULK-001',
    ]);

    // Controleer de catalogusvermelding van de tweede postzegel.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'TEST-BULK-002',
    ]);

    // Haal beide aangemaakte catalogusvermeldingen op.
    $firstCatalogEntry = $this->stamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    $secondCatalogEntry = $secondStamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer de cataloguswaarde van de eerste postzegel.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $firstCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    // Controleer de cataloguswaarde van de tweede postzegel.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $secondCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 25.75,
        'currency' => 'EUR',
    ]);

    // Controleer dat alle records precies eenmaal zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 2);
    $this->assertDatabaseCount('stamp_catalog_values', 2);
});

test('een bestaand catalogusnummer wordt gewijzigd zonder een dubbele catalogusvermelding aan te maken', function () {
    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-OUD-001',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Wijzig uitsluitend het bestaande catalogusnummer.
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
                        'catalog_number' => 'TEST-NIEUW-001',
                        'values' => [
                            $this->condition->id => '12.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding is bijgewerkt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-NIEUW-001',
    ]);

    // Controleer dat het oude catalogusnummer niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-OUD-001',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer dat er geen dubbele records zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een bestaande cataloguswaarde wordt gewijzigd en automatisch ter controle gemarkeerd', function () {
    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-004',
    ]);

    // Maak een bestaande gecontroleerde cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Wijzig uitsluitend de bestaande cataloguswaarde.
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
                        'catalog_number' => 'TEST-004',
                        'values' => [
                            $this->condition->id => '18.75',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-004',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde is bijgewerkt.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 18.75,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Controleer dat de oude cataloguswaarde niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'catalog_value' => 12.50,
    ]);

    // Vernieuw het model met de opgeslagen databasegegevens.
    $catalogValue->refresh();

    // Controleer de decimale waarde en de automatische controlemarkering.
    expect($catalogValue->catalog_value)
        ->toBe('18.75')
        ->and($catalogValue->needs_review)
        ->toBeTrue();

    // Controleer dat er geen dubbele records zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een bestaand catalogusnummer en een bestaande cataloguswaarde worden tegelijkertijd gewijzigd', function () {
    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-OUD-005',
    ]);

    // Maak een bestaande gecontroleerde cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Wijzig het catalogusnummer en de cataloguswaarde tegelijkertijd.
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
                        'catalog_number' => 'TEST-NIEUW-005',
                        'values' => [
                            $this->condition->id => '22.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding is bijgewerkt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-NIEUW-005',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde is bijgewerkt.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 22.50,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Controleer dat het oude catalogusnummer niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_number' => 'TEST-OUD-005',
    ]);

    // Controleer dat de oude cataloguswaarde niet meer aanwezig is.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'catalog_value' => 15.00,
    ]);

    // Vernieuw beide modellen met de opgeslagen databasegegevens.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer beide wijzigingen en de automatische controlemarkering.
    expect($catalogEntry->catalog_number)
        ->toBe('TEST-NIEUW-005')
        ->and($catalogValue->catalog_value)
        ->toBe('22.50')
        ->and($catalogValue->needs_review)
        ->toBeTrue();

    // Controleer dat er geen dubbele records zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een ongewijzigde bulkbewerking veroorzaakt geen ongewenste wijzigingen of dubbele records', function () {
    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-006',
    ]);

    // Maak een bestaande gecontroleerde cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 25.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke databaseattributen.
    $originalEntryAttributes = $catalogEntry->fresh()->getAttributes();
    $originalValueAttributes = $catalogValue->fresh()->getAttributes();

    // Sla dezelfde catalogusgegevens opnieuw op.
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
                        'catalog_number' => 'TEST-006',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Vernieuw beide modellen met de opgeslagen databasegegevens.
    $catalogEntry->refresh();
    $catalogValue->refresh();

    // Controleer dat de catalogusgegevens inhoudelijk ongewijzigd zijn.
    expect($catalogEntry->catalog_number)
        ->toBe('TEST-006')
        ->and($catalogValue->catalog_value)
        ->toBe('25.00')
        ->and($catalogValue->currency)
        ->toBe('EUR')
        ->and($catalogValue->needs_review)
        ->toBeFalse();

    // Haal de huidige databaseattributen op.
    $currentEntryAttributes = $catalogEntry->getAttributes();
    $currentValueAttributes = $catalogValue->getAttributes();

    // Negeer eventuele wijzigingen van de technische tijdstempels.
    unset(
        $originalEntryAttributes['updated_at'],
        $originalValueAttributes['updated_at'],
        $currentEntryAttributes['updated_at'],
        $currentValueAttributes['updated_at']
    );

    // Controleer dat alle overige databaseattributen ongewijzigd zijn.
    expect($currentEntryAttributes)
        ->toEqual($originalEntryAttributes)
        ->and($currentValueAttributes)
        ->toEqual($originalValueAttributes);

    // Controleer dat er geen dubbele records zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een bestaande cataloguswaarde wordt verwijderd terwijl de catalogusvermelding behouden blijft', function () {
    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-007',
    ]);

    // Maak een bestaande cataloguswaarde aan.
    $catalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 35.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak het waardeveld leeg, maar behoud het catalogusnummer.
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
                        'catalog_number' => 'TEST-007',
                        'values' => [
                            $this->condition->id => null,
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-007',
    ]);

    // Controleer dat de oorspronkelijke cataloguswaarde is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $catalogValue->id,
    ]);

    // Controleer dat er geen vervangende cataloguswaarde is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
    ]);

    // Controleer dat de catalogusvermelding geen cataloguswaarden meer heeft.
    expect($catalogEntry->values()->count())->toBe(0);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 0);
});

test('een cataloguswaarde wordt verwijderd terwijl een andere cataloguswaarde behouden blijft', function () {
    // Maak een tweede waarderingscategorie aan.
    $secondCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak een algemene waarderingsregel voor de tweede categorie aan.
    CatalogConditionRule::create([
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $secondCondition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
        'sort_order' => 2,
    ]);

    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-008',
    ]);

    // Maak de cataloguswaarde voor postfris aan.
    $firstCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 45.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak de cataloguswaarde voor gestempeld aan.
    $secondCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $secondCondition->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Verwijder uitsluitend de cataloguswaarde voor postfris.
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
                        'catalog_number' => 'TEST-008',
                        'values' => [
                            $this->condition->id => null,
                            $secondCondition->id => '20.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-008',
    ]);

    // Controleer dat de cataloguswaarde voor postfris is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $firstCatalogValue->id,
    ]);

    // Controleer dat er geen vervangende waarde voor postfris is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
    ]);

    // Controleer dat de cataloguswaarde voor gestempeld behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $secondCatalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $secondCondition->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Vernieuw de behouden cataloguswaarde.
    $secondCatalogValue->refresh();

    // Controleer de waarde en de ongewijzigde controlemarkering.
    expect($secondCatalogValue->catalog_value)
        ->toBe('20.00')
        ->and($secondCatalogValue->needs_review)
        ->toBeFalse();

    // Controleer dat de catalogusvermelding nog precies één waarde heeft.
    expect($catalogEntry->values()->count())->toBe(1);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een volledige catalogusvermelding wordt inclusief alle bijbehorende cataloguswaarden verwijderd', function () {
    // Maak een tweede waarderingscategorie aan.
    $secondCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak een algemene waarderingsregel voor de tweede categorie aan.
    CatalogConditionRule::create([
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $secondCondition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
        'sort_order' => 2,
    ]);

    // Maak een bestaande catalogusvermelding aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-009',
    ]);

    // Maak de cataloguswaarde voor postfris aan.
    $firstCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 55.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak de cataloguswaarde voor gestempeld aan.
    $secondCatalogValue = $catalogEntry->values()->create([
        'condition_id' => $secondCondition->id,
        'catalog_value' => 30.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak het catalogusnummer en alle cataloguswaarden leeg.
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

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de cataloguswaarde voor postfris is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $firstCatalogValue->id,
    ]);

    // Controleer dat de cataloguswaarde voor gestempeld is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $secondCatalogValue->id,
    ]);

    // Controleer dat de oorspronkelijke catalogusvermelding is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
    ]);

    // Controleer dat er geen vervangende catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat beide waarderingscategorieën behouden blijven.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    $this->assertDatabaseHas('conditions', [
        'id' => $secondCondition->id,
    ]);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);
});

test('een catalogusvermelding zonder bijbehorende cataloguswaarden wordt correct verwijderd', function () {
    // Maak een bestaande catalogusvermelding zonder cataloguswaarden aan.
    $catalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-010',
    ]);

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Maak het catalogusnummer leeg.
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
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de oorspronkelijke catalogusvermelding is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
    ]);

    // Controleer dat er geen vervangende catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);
});

test('een catalogusvermelding wordt verwijderd zonder catalogusgegevens van andere postzegels te beïnvloeden', function () {
    // Maak een tweede postzegel binnen hetzelfde land aan.
    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    // Maak een catalogusvermelding voor de eerste postzegel aan.
    $firstCatalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-011-A',
    ]);

    // Maak een cataloguswaarde voor de eerste postzegel aan.
    $firstCatalogValue = $firstCatalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 40.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak een catalogusvermelding voor de tweede postzegel aan.
    $secondCatalogEntry = $secondStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-011-B',
    ]);

    // Maak een cataloguswaarde voor de tweede postzegel aan.
    $secondCatalogValue = $secondCatalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 65.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Verwijder uitsluitend de catalogusgegevens van de eerste postzegel.
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
                        ],
                    ],
                    $secondStamp->id => [
                        'catalog_number' => 'TEST-011-B',
                        'values' => [
                            $this->condition->id => '65.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de cataloguswaarde van de eerste postzegel is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $firstCatalogValue->id,
    ]);

    // Controleer dat de catalogusvermelding van de eerste postzegel is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $firstCatalogEntry->id,
    ]);

    // Controleer dat er geen vervangende catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat de catalogusvermelding van de tweede postzegel behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $secondCatalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'TEST-011-B',
    ]);

    // Controleer dat de cataloguswaarde van de tweede postzegel behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $secondCatalogValue->id,
        'stamp_catalog_entry_id' => $secondCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 65.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Vernieuw de behouden cataloguswaarde.
    $secondCatalogValue->refresh();

    // Controleer de ongewijzigde waarde en controlemarkering.
    expect($secondCatalogValue->catalog_value)
        ->toBe('65.00')
        ->and($secondCatalogValue->needs_review)
        ->toBeFalse();

    // Controleer dat de eerste postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
    ]);

    // Controleer dat de tweede postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $secondStamp->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een catalogusvermelding wordt verwijderd zonder catalogusgegevens in andere catalogusedities te beïnvloeden', function () {
    // Maak een tweede editie van dezelfde catalogus aan.
    $secondEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak een catalogusvermelding voor de editie van 2026 aan.
    $firstCatalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->edition->id,
        'catalog_number' => 'TEST-012-2026',
    ]);

    // Maak een cataloguswaarde voor de editie van 2026 aan.
    $firstCatalogValue = $firstCatalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 75.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak een catalogusvermelding voor de editie van 2025 aan.
    $secondCatalogEntry = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $secondEdition->id,
        'catalog_number' => 'TEST-012-2025',
    ]);

    // Maak een cataloguswaarde voor de editie van 2025 aan.
    $secondCatalogValue = $secondCatalogEntry->values()->create([
        'condition_id' => $this->condition->id,
        'catalog_value' => 60.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke attributen van de editie van 2025.
    $originalEntryAttributes = $secondCatalogEntry->fresh()->getAttributes();
    $originalValueAttributes = $secondCatalogValue->fresh()->getAttributes();

    // Verwijder uitsluitend de catalogusgegevens uit de editie van 2026.
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
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de doorverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de cataloguswaarde uit 2026 is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $firstCatalogValue->id,
    ]);

    // Controleer dat de catalogusvermelding uit 2026 is verwijderd.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $firstCatalogEntry->id,
    ]);

    // Controleer dat er geen vervangende catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat de catalogusvermelding uit 2025 behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $secondCatalogEntry->id,
        'catalog_edition_id' => $secondEdition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-012-2025',
    ]);

    // Controleer dat de cataloguswaarde uit 2025 behouden blijft.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $secondCatalogValue->id,
        'stamp_catalog_entry_id' => $secondCatalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 60.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Vernieuw de behouden catalogusgegevens.
    $secondCatalogEntry->refresh();
    $secondCatalogValue->refresh();

    // Controleer dat alle attributen van de editie van 2025 ongewijzigd zijn.
    expect($secondCatalogEntry->getAttributes())
        ->toEqual($originalEntryAttributes)
        ->and($secondCatalogValue->getAttributes())
        ->toEqual($originalValueAttributes);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
    ]);

    // Controleer dat beide catalogusedities behouden blijven.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    $this->assertDatabaseHas('catalog_editions', [
        'id' => $secondEdition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer het definitieve aantal records.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
    $this->assertDatabaseCount('catalog_editions', 2);
});

test('een cataloguswaarde zonder catalogusnummer mag niet worden opgeslagen', function () {
    // Probeer een cataloguswaarde zonder catalogusnummer op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
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
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een cataloguswaarde van nul zonder catalogusnummer mag niet worden opgeslagen', function () {
    // Probeer een cataloguswaarde van nul zonder catalogusnummer op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
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
                            $this->condition->id => '0.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een negatieve cataloguswaarde mag niet worden opgeslagen', function () {
    // Probeer een negatieve cataloguswaarde op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-013',
                        'values' => [
                            $this->condition->id => '-12.50',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de cataloguswaarde aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.values.{$this->condition->id}",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een niet-numerieke cataloguswaarde mag niet worden opgeslagen', function () {
    // Probeer een niet-numerieke cataloguswaarde op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-014',
                        'values' => [
                            $this->condition->id => 'ongeldige waarde',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de cataloguswaarde aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.values.{$this->condition->id}",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een cataloguswaarde met meer dan twee decimalen mag niet worden opgeslagen', function () {
    // Probeer een cataloguswaarde met drie decimalen op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-015',
                        'values' => [
                            $this->condition->id => '12.345',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de cataloguswaarde aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.values.{$this->condition->id}",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een catalogusnummer met meer dan 100 tekens mag niet worden opgeslagen', function () {
    // Maak een ongeldig catalogusnummer van 101 tekens aan.
    $catalogNumber = str_repeat('A', 101);

    // Probeer het te lange catalogusnummer op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => $catalogNumber,
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor het catalogusnummer aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.catalog_number",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een catalogusnummer van exact 100 tekens mag worden opgeslagen', function () {
    // Maak een geldig catalogusnummer van exact 100 tekens aan.
    $catalogNumber = str_repeat('A', 100);

    // Probeer het catalogusnummer en de cataloguswaarde op te slaan.
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
                        'catalog_number' => $catalogNumber,
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er geen validatiefouten aanwezig zijn.
    $response->assertSessionHasNoErrors();

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er een succesmelding aanwezig is.
    $response->assertSessionHas('success');

    // Controleer dat de catalogusvermelding volledig is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => $catalogNumber,
    ]);

    // Haal de opgeslagen catalogusvermelding op.
    $catalogEntry = $this->stamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer dat het catalogusnummer niet is afgekapt.
    expect($catalogEntry->catalog_number)
        ->toBe($catalogNumber);

    // Controleer dat het opgeslagen catalogusnummer exact 100 tekens bevat.
    expect(strlen($catalogEntry->catalog_number))
        ->toBe(100);

    // Controleer dat de bijbehorende cataloguswaarde is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => 25.00,
        'currency' => 'EUR',
    ]);

    // Controleer dat er geen dubbele records zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);
});

test('een catalogusnummer met een ongeldig gegevenstype mag niet worden opgeslagen', function () {
    // Probeer een array als catalogusnummer op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => [
                            'TEST-016-A',
                            'TEST-016-B',
                        ],
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor het catalogusnummer aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.catalog_number",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('cataloguswaarden met een ongeldige gegevensstructuur mogen niet worden opgeslagen', function () {
    // Probeer cataloguswaarden als tekst in plaats van een array op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-017',
                        'values' => '25.00',
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de cataloguswaarden aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}.values",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('postzegelgegevens met een ongeldige gegevensstructuur mogen niet worden opgeslagen', function () {
    // Probeer postzegelgegevens als tekst in plaats van een array op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => 'ongeldige postzegelgegevens',
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de postzegelgegevens aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een bulkbewerking met een ongeldige bovenliggende gegevensstructuur mag niet worden opgeslagen', function () {
    // Probeer de bovenliggende postzegelgegevens als tekst op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => 'ongeldige bulkbewerkingsgegevens',
            ]
        );

    // Controleer dat er een validatiefout voor het hoofdveld aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een bulkbewerking zonder het verplichte hoofdveld stamps mag niet worden opgeslagen', function () {
    // Probeer een bulkbewerking zonder postzegelgegevens op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            []
        );

    // Controleer dat er een validatiefout voor het verplichte hoofdveld aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een bulkbewerking met een lege bovenliggende array mag niet worden opgeslagen', function () {
    // Probeer een bulkbewerking met een lege postzegelarray op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [],
            ]
        );

    // Controleer dat er een validatiefout voor het lege hoofdveld aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een bulkbewerking met een lege individuele postzegelarray mag niet worden opgeslagen', function () {
    // Probeer een bulkbewerking met lege individuele postzegelgegevens op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [],
                ],
            ]
        );

    // Controleer dat er een validatiefout voor de lege postzegelgegevens aanwezig is.
    $response->assertSessionHasErrors([
        "stamps.{$this->stamp->id}",
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een postzegel uit een land dat niet aan de catalogus is gekoppeld mag niet via een bulkbewerking worden opgeslagen', function () {
    // Maak een tweede land aan dat niet aan de catalogus wordt gekoppeld.
    $otherCountry = Country::create([
        'name' => 'Testland Buiten Catalogus',
        'iso_code' => 'BC',
        'entity_type' => 'country',
        'slug' => 'testland-buiten-catalogus',
        'is_active' => true,
    ]);

    // Maak een postzegel aan die bij het niet-gekoppelde land hoort.
    $otherStamp = Stamp::factory()->create([
        'country_id' => $otherCountry->id,
        'issue_year' => 2025,
    ]);

    // Controleer de bestaande landkoppelingen van de catalogus.
    expect(
        $this->catalog->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalog->countries()
            ->where('countries.id', $otherCountry->id)
            ->exists()
    )->toBeFalse();

    // Probeer catalogusgegevens voor de niet-toegestane postzegel op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $otherStamp->id => [
                        'catalog_number' => 'TEST-020',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $otherStamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de niet-toegestane postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $otherStamp->id,
        'country_id' => $otherCountry->id,
    ]);

    // Controleer dat het niet-gekoppelde land behouden blijft.
    $this->assertDatabaseHas('countries', [
        'id' => $otherCountry->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat het tweede land niet automatisch is gekoppeld.
    expect(
        $this->catalog->countries()
            ->where('countries.id', $otherCountry->id)
            ->exists()
    )->toBeFalse();
});

test('een niet-bestaande postzegel-ID mag niet via een bulkbewerking worden opgeslagen', function () {
    // Bepaal een postzegel-ID die niet in de database bestaat.
    $nonExistingStampId = (int) Stamp::query()->max('id') + 1;

    // Controleer dat deze postzegel daadwerkelijk niet bestaat.
    $this->assertDatabaseMissing('stamps', [
        'id' => $nonExistingStampId,
    ]);

    // Probeer catalogusgegevens voor een niet-bestaande postzegel op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $nonExistingStampId => [
                        'catalog_number' => 'TEST-021',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $nonExistingStampId,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de niet-bestaande postzegel niet automatisch is aangemaakt.
    $this->assertDatabaseMissing('stamps', [
        'id' => $nonExistingStampId,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een cataloguseditie die bij een andere catalogus hoort mag niet via de bulkbewerkingspagina worden geopend', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere Testcatalogus Bulkbewerking',
        'abbreviation' => 'ATB',
    ]);

    // Koppel hetzelfde testland aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie voor de tweede catalogus aan.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Andere Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Controleer dat de tweede cataloguseditie bij de tweede catalogus hoort.
    expect($otherEdition->catalog_id)
        ->toBe($otherCatalog->id)
        ->not->toBe($this->catalog->id);

    // Probeer de bulkbewerkingspagina met een ongeldige cataloguscombinatie te openen.
    $response = $this
        ->actingAs($this->user)
        ->get(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $otherEdition,
            ])
        );

    // Controleer dat de ongeldige combinatie wordt geweigerd.
    $response->assertNotFound();

    // Controleer dat er geen catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
    ]);

    // Controleer dat de tweede catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $otherCatalog->id,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de tweede cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $otherEdition->id,
        'catalog_id' => $otherCatalog->id,
    ]);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);
});

test('een cataloguseditie die bij een andere catalogus hoort mag niet via een bulkbewerking worden gewijzigd', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere Testcatalogus Bulkbewerking',
        'abbreviation' => 'ATB',
    ]);

    // Koppel hetzelfde testland aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie voor de tweede catalogus aan.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Andere Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak ook voor de tweede catalogus een geldige waarderingsregel aan.
    CatalogConditionRule::create([
        'catalog_id' => $otherCatalog->id,
        'country_id' => null,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
        'sort_order' => 1,
    ]);

    // Controleer dat de tweede cataloguseditie bij de tweede catalogus hoort.
    expect($otherEdition->catalog_id)
        ->toBe($otherCatalog->id)
        ->not->toBe($this->catalog->id);

    // Probeer catalogusgegevens met een ongeldige cataloguscombinatie op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $otherEdition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-022',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat de ongeldige combinatie wordt geweigerd.
    $response->assertNotFound();

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding in de eerste editie is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusvermelding in de tweede editie is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $otherEdition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
    ]);

    // Controleer dat de tweede catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $otherCatalog->id,
    ]);

    // Controleer dat de oorspronkelijke cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de tweede cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $otherEdition->id,
        'catalog_id' => $otherCatalog->id,
    ]);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);
});

test('een bestaande waarderingscategorie waarvoor geen toepasselijke waarderingsregel bestaat mag niet via een bulkbewerking worden opgeslagen', function () {
    // Maak een tweede waarderingscategorie aan.
    $otherCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie is toegestaan.
    expect(
        $this->catalog->conditionRules()
            ->where('condition_id', $this->condition->id)
            ->exists()
    )->toBeTrue();

    // Controleer dat voor de tweede waarderingscategorie geen regel bestaat.
    expect(
        $this->catalog->conditionRules()
            ->where('condition_id', $otherCondition->id)
            ->exists()
    )->toBeFalse();

    // Probeer een cataloguswaarde voor de niet-toegestane categorie op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-023',
                        'values' => [
                            $otherCondition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de niet-toegestane waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $otherCondition->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel behouden blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);

    // Controleer dat er geen nieuwe waarderingsregel is aangemaakt.
    $this->assertDatabaseMissing('catalog_condition_rules', [
        'catalog_id' => $this->catalog->id,
        'condition_id' => $otherCondition->id,
    ]);
});

test('een niet-bestaande waarderingscategorie-ID mag niet via een bulkbewerking worden opgeslagen', function () {
    // Bepaal een waarderingscategorie-ID die niet in de database bestaat.
    $nonExistingConditionId = (int) Condition::query()->max('id') + 1;

    // Controleer dat deze waarderingscategorie daadwerkelijk niet bestaat.
    $this->assertDatabaseMissing('conditions', [
        'id' => $nonExistingConditionId,
    ]);

    // Probeer een cataloguswaarde voor een niet-bestaande categorie op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-024',
                        'values' => [
                            $nonExistingConditionId => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de niet-bestaande categorie niet automatisch is aangemaakt.
    $this->assertDatabaseMissing('conditions', [
        'id' => $nonExistingConditionId,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel behouden blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
    ]);

    // Controleer dat er geen ongewenste waarderingsregel is aangemaakt.
    $this->assertDatabaseMissing('catalog_condition_rules', [
        'catalog_id' => $this->catalog->id,
        'condition_id' => $nonExistingConditionId,
    ]);
});

test('een cataloguswaarde voor een postzegel met een uitgiftejaar voor het beginjaar van de waarderingsregel mag niet via een bulkbewerking worden opgeslagen', function () {
    // Maak een postzegel aan met een uitgiftejaar voor de geldigheidsperiode.
    $olderStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2019,
    ]);

    // Controleer de geldigheidsperiode van de waarderingsregel.
    expect($this->conditionRule->issue_year_from)->toBe(2020);
    expect($this->conditionRule->issue_year_to)->toBe(2030);

    // Controleer dat het uitgiftejaar voor het beginjaar ligt.
    expect($olderStamp->issue_year)
        ->toBeLessThan($this->conditionRule->issue_year_from);

    // Probeer een cataloguswaarde buiten de geldigheidsperiode op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $olderStamp->id => [
                        'catalog_number' => 'TEST-025',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $olderStamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat de oudere postzegel ongewijzigd behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $olderStamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2019,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);
});

test('een cataloguswaarde voor een postzegel met een uitgiftejaar na het eindjaar van de waarderingsregel mag niet via een bulkbewerking worden opgeslagen', function () {
    // Maak een postzegel aan met een uitgiftejaar na de geldigheidsperiode.
    $newerStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2031,
    ]);

    // Controleer de geldigheidsperiode van de waarderingsregel.
    expect($this->conditionRule->issue_year_from)->toBe(2020);
    expect($this->conditionRule->issue_year_to)->toBe(2030);

    // Controleer dat het uitgiftejaar na het eindjaar ligt.
    expect($newerStamp->issue_year)
        ->toBeGreaterThan($this->conditionRule->issue_year_to);

    // Probeer een cataloguswaarde buiten de geldigheidsperiode op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $newerStamp->id => [
                        'catalog_number' => 'TEST-026',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $newerStamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat de nieuwere postzegel ongewijzigd behouden blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $newerStamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2031,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);
});

test('een cataloguswaarde voor een postzegel met een uitgiftejaar exact gelijk aan het beginjaar van de waarderingsregel mag via een bulkbewerking worden opgeslagen', function () {
    // Maak een postzegel aan met een uitgiftejaar gelijk aan het beginjaar.
    $boundaryStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2020,
    ]);

    // Controleer de geldigheidsperiode van de waarderingsregel.
    expect($this->conditionRule->issue_year_from)->toBe(2020);
    expect($this->conditionRule->issue_year_to)->toBe(2030);

    // Controleer dat het uitgiftejaar exact gelijk is aan het beginjaar.
    expect($boundaryStamp->issue_year)
        ->toBe($this->conditionRule->issue_year_from);

    // Sla een cataloguswaarde op voor de postzegel op de ondergrens.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $boundaryStamp->id => [
                        'catalog_number' => 'TEST-027',
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

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = $boundaryStamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer het opgeslagen catalogusnummer.
    expect($catalogEntry->catalog_number)->toBe('TEST-027');

    // Controleer dat de catalogusvermelding correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $boundaryStamp->id,
        'catalog_number' => 'TEST-027',
    ]);

    // Haal de aangemaakte cataloguswaarde op.
    $catalogValue = $catalogEntry->values()
        ->where('condition_id', $this->condition->id)
        ->firstOrFail();

    // Controleer de opgeslagen cataloguswaarde en valuta.
    expect($catalogValue->catalog_value)->toBe('25.00');
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
    ]);

    // Controleer dat er geen dubbele catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de oorspronkelijke postzegel geen catalogusvermelding heeft gekregen.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat beide postzegels ongewijzigd behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    $this->assertDatabaseHas('stamps', [
        'id' => $boundaryStamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2020,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);
});

test('een cataloguswaarde voor een postzegel met een uitgiftejaar exact gelijk aan het eindjaar van de waarderingsregel mag via een bulkbewerking worden opgeslagen', function () {
    // Maak een postzegel aan met een uitgiftejaar gelijk aan het eindjaar.
    $boundaryStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2030,
    ]);

    // Controleer de geldigheidsperiode van de waarderingsregel.
    expect($this->conditionRule->issue_year_from)->toBe(2020);
    expect($this->conditionRule->issue_year_to)->toBe(2030);

    // Controleer dat het uitgiftejaar exact gelijk is aan het eindjaar.
    expect($boundaryStamp->issue_year)
        ->toBe($this->conditionRule->issue_year_to);

    // Sla een cataloguswaarde op voor de postzegel op de bovengrens.
    $response = $this
        ->actingAs($this->user)
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $boundaryStamp->id => [
                        'catalog_number' => 'TEST-028',
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

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = $boundaryStamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer het opgeslagen catalogusnummer.
    expect($catalogEntry->catalog_number)->toBe('TEST-028');

    // Controleer dat de catalogusvermelding correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $boundaryStamp->id,
        'catalog_number' => 'TEST-028',
    ]);

    // Haal de aangemaakte cataloguswaarde op.
    $catalogValue = $catalogEntry->values()
        ->where('condition_id', $this->condition->id)
        ->firstOrFail();

    // Controleer de opgeslagen cataloguswaarde en valuta.
    expect($catalogValue->catalog_value)->toBe('25.00');
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
    ]);

    // Controleer dat er geen dubbele catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de oorspronkelijke postzegel geen catalogusvermelding heeft gekregen.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat beide postzegels ongewijzigd behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    $this->assertDatabaseHas('stamps', [
        'id' => $boundaryStamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2030,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);
});

test('een cataloguswaarde waarvoor uitsluitend een waarderingsregel voor een ander land bestaat mag niet via een bulkbewerking worden opgeslagen', function () {
    // Maak een tweede land aan.
    $otherCountry = Country::create([
        'name' => 'Testland Andere Waarderingsregel',
        'iso_code' => 'AW',
        'entity_type' => 'country',
        'slug' => 'testland-andere-waarderingsregel',
        'is_active' => true,
    ]);

    // Koppel ook het tweede land aan de catalogus.
    $this->catalog->countries()->attach($otherCountry->id);

    // Maak de bestaande waarderingsregel uitsluitend geldig voor het tweede land.
    $this->conditionRule->update([
        'country_id' => $otherCountry->id,
    ]);

    // Controleer dat beide landen aan de catalogus zijn gekoppeld.
    expect(
        $this->catalog->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalog->countries()
            ->where('countries.id', $otherCountry->id)
            ->exists()
    )->toBeTrue();

    // Controleer dat de waarderingsregel uitsluitend voor het tweede land geldt.
    expect($this->conditionRule->country_id)
        ->toBe($otherCountry->id);

    // Controleer dat de postzegel tot een ander land behoort.
    expect($this->stamp->country_id)
        ->not->toBe($this->conditionRule->country_id);

    // Controleer dat er geen algemene waarderingsregel bestaat.
    expect(
        $this->catalog->conditionRules()
            ->where('condition_id', $this->condition->id)
            ->whereNull('country_id')
            ->exists()
    )->toBeFalse();

    // Probeer een cataloguswaarde voor de oorspronkelijke postzegel op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-029',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat er geen catalogusvermelding is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat er geen catalogusgegevens zijn opgeslagen.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat beide landen behouden blijven.
    $this->assertDatabaseHas('countries', [
        'id' => $this->country->id,
    ]);

    $this->assertDatabaseHas('countries', [
        'id' => $otherCountry->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de landspecifieke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'country_id' => $otherCountry->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);

    // Controleer dat er geen aanvullende waarderingsregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_condition_rules', 1);
});

test('een cataloguswaarde waarvoor een toepasselijke landspecifieke waarderingsregel bestaat mag via een bulkbewerking worden opgeslagen', function () {
    // Maak een tweede land aan.
    $otherCountry = Country::create([
        'name' => 'Testland Landspecifieke Waarderingsregel',
        'iso_code' => 'LW',
        'entity_type' => 'country',
        'slug' => 'testland-landspecifieke-waarderingsregel',
        'is_active' => true,
    ]);

    // Koppel ook het tweede land aan de catalogus.
    $this->catalog->countries()->attach($otherCountry->id);

    // Maak de bestaande waarderingsregel uitsluitend geldig voor het oorspronkelijke land.
    $this->conditionRule->update([
        'country_id' => $this->country->id,
    ]);

    // Controleer dat beide landen aan de catalogus zijn gekoppeld.
    expect(
        $this->catalog->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalog->countries()
            ->where('countries.id', $otherCountry->id)
            ->exists()
    )->toBeTrue();

    // Controleer dat de waarderingsregel voor het oorspronkelijke land geldt.
    expect($this->conditionRule->country_id)
        ->toBe($this->country->id);

    // Controleer dat de postzegel tot hetzelfde land behoort.
    expect($this->stamp->country_id)
        ->toBe($this->conditionRule->country_id);

    // Controleer dat er geen algemene waarderingsregel bestaat.
    expect(
        $this->catalog->conditionRules()
            ->where('condition_id', $this->condition->id)
            ->whereNull('country_id')
            ->exists()
    )->toBeFalse();

    // Sla een cataloguswaarde op waarvoor de landspecifieke regel geldig is.
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
                        'catalog_number' => 'TEST-030',
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

    // Haal de aangemaakte catalogusvermelding op.
    $catalogEntry = $this->stamp->catalogEntries()
        ->where('catalog_edition_id', $this->edition->id)
        ->firstOrFail();

    // Controleer het opgeslagen catalogusnummer.
    expect($catalogEntry->catalog_number)->toBe('TEST-030');

    // Controleer dat de catalogusvermelding correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $catalogEntry->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
        'catalog_number' => 'TEST-030',
    ]);

    // Haal de aangemaakte cataloguswaarde op.
    $catalogValue = $catalogEntry->values()
        ->where('condition_id', $this->condition->id)
        ->firstOrFail();

    // Controleer de opgeslagen cataloguswaarde en valuta.
    expect($catalogValue->catalog_value)->toBe('25.00');
    expect($catalogValue->currency)->toBe('EUR');

    // Controleer dat de cataloguswaarde correct is opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $catalogValue->id,
        'stamp_catalog_entry_id' => $catalogEntry->id,
        'condition_id' => $this->condition->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
    ]);

    // Controleer dat er geen dubbele catalogusgegevens zijn aangemaakt.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);
    $this->assertDatabaseCount('stamp_catalog_values', 1);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat beide landen behouden blijven.
    $this->assertDatabaseHas('countries', [
        'id' => $this->country->id,
    ]);

    $this->assertDatabaseHas('countries', [
        'id' => $otherCountry->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de landspecifieke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'country_id' => $this->country->id,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);

    // Controleer dat er geen aanvullende waarderingsregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_condition_rules', 1);
});

test('een gecombineerde bulkbewerking met geldige en ongeldige postzegels wordt volledig geweigerd zonder gedeeltelijke opslag of geschiedenisregistratie', function () {
    // Maak een tweede postzegel aan buiten de geldigheidsperiode.
    $invalidStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2019,
    ]);

    // Controleer dat beide postzegels tot hetzelfde land behoren.
    expect($this->stamp->country_id)
        ->toBe($invalidStamp->country_id);

    // Controleer de geldigheidsperiode van de waarderingsregel.
    expect($this->conditionRule->issue_year_from)->toBe(2020);
    expect($this->conditionRule->issue_year_to)->toBe(2030);

    // Controleer dat de oorspronkelijke postzegel binnen de geldigheidsperiode valt.
    expect($this->stamp->issue_year)
        ->toBeGreaterThanOrEqual($this->conditionRule->issue_year_from);

    expect($this->stamp->issue_year)
        ->toBeLessThanOrEqual($this->conditionRule->issue_year_to);

    // Controleer dat de tweede postzegel buiten de geldigheidsperiode valt.
    expect($invalidStamp->issue_year)
        ->toBeLessThan($this->conditionRule->issue_year_from);

    // Controleer dat er vooraf geen catalogusgegevens aanwezig zijn.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er vooraf geen geschiedenisregistratie aanwezig is.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Probeer geldige en ongeldige catalogusgegevens tegelijkertijd op te slaan.
    $response = $this
        ->actingAs($this->user)
        ->from(
            route('backend.catalogs.editions.bulk-edit', [
                $this->catalog,
                $this->edition,
            ])
        )
        ->patch(
            route('backend.catalogs.editions.bulk-update', [
                $this->catalog,
                $this->edition,
            ]),
            [
                'stamps' => [
                    $this->stamp->id => [
                        'catalog_number' => 'TEST-031',
                        'values' => [
                            $this->condition->id => '25.00',
                        ],
                    ],
                    $invalidStamp->id => [
                        'catalog_number' => 'TEST-032',
                        'values' => [
                            $this->condition->id => '35.00',
                        ],
                    ],
                ],
            ]
        );

    // Controleer dat er een validatiefout aanwezig is.
    $response->assertSessionHasErrors([
        'stamps',
    ]);

    // Controleer de terugverwijzing naar de bulkbewerkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.bulk-edit', [
            $this->catalog,
            $this->edition,
        ])
    );

    // Controleer dat er geen succesmelding aanwezig is.
    $response->assertSessionMissing('success');

    // Controleer dat ook de geldige catalogusvermelding niet is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $this->stamp->id,
    ]);

    // Controleer dat de ongeldige catalogusvermelding niet is aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $invalidStamp->id,
    ]);

    // Controleer dat er geen gedeeltelijke opslag heeft plaatsgevonden.
    $this->assertDatabaseCount('stamp_catalog_entries', 0);
    $this->assertDatabaseCount('stamp_catalog_values', 0);

    // Controleer dat er geen geschiedenisregistratie heeft plaatsgevonden.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat de oorspronkelijke postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Controleer dat de tweede postzegel ongewijzigd blijft.
    $this->assertDatabaseHas('stamps', [
        'id' => $invalidStamp->id,
        'country_id' => $this->country->id,
        'issue_year' => 2019,
    ]);

    // Controleer dat het oorspronkelijke land behouden blijft.
    $this->assertDatabaseHas('countries', [
        'id' => $this->country->id,
    ]);

    // Controleer dat de catalogus behouden blijft.
    $this->assertDatabaseHas('catalogs', [
        'id' => $this->catalog->id,
    ]);

    // Controleer dat de cataloguseditie behouden blijft.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->edition->id,
        'catalog_id' => $this->catalog->id,
    ]);

    // Controleer dat de waarderingscategorie behouden blijft.
    $this->assertDatabaseHas('conditions', [
        'id' => $this->condition->id,
    ]);

    // Controleer dat de oorspronkelijke waarderingsregel ongewijzigd blijft.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'id' => $this->conditionRule->id,
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $this->condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2030,
    ]);

    // Controleer dat er geen aanvullende waarderingsregels zijn aangemaakt.
    $this->assertDatabaseCount('catalog_condition_rules', 1);
});
