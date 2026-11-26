<?php

use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogChangeLog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Gebruik Nederlands als standaardtaal.
    app()->setLocale('nl');

    // Maak een gewone gebruiker aan.
    $this->user = User::factory()->create([
        'is_admin' => false,
        'name' => 'Gewone testgebruiker',
    ]);

    // Maak een beheerder aan.
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Testbeheerder',
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Geschiedeniscontroller',
        'abbreviation' => 'TGC',
    ]);

    // Maak een cataloguseditie aan.
    $this->edition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een geschiedenisbatch aan.
    $this->batch = CatalogChangeBatch::create([
        'catalog_edition_id' => $this->edition->id,
        'user_id' => $this->admin->id,
        'user_name' => 'Historische testbeheerder',
        'operation' => 'bulk_edit',
        'description' => 'Testbewerking catalogusgeschiedenis',
        'catalog_name' => $this->catalog->name,
        'catalog_edition_name' => $this->edition->edition_name,
    ]);
});

test('een gewone gebruiker kan het geschiedenisoverzicht openen zonder gebruikersgegevens te zien', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('backend.catalogs.editions.history.index', [
            $this->catalog,
            $this->edition,
        ]));

    $response->assertOk();

    $response->assertSee('Wijzigingsgeschiedenis');
    $response->assertSee('Testbewerking catalogusgeschiedenis');

    // De historische gebruikersnaam mag niet zichtbaar zijn.
    $response->assertDontSee('Historische testbeheerder');
    $response->assertDontSee('Testbeheerder');
});

test('een beheerder kan het geschiedenisoverzicht openen inclusief gebruikersgegevens', function () {
    $response = $this
        ->actingAs($this->admin)
        ->get(route('backend.catalogs.editions.history.index', [
            $this->catalog,
            $this->edition,
        ]));

    $response->assertOk();

    $response->assertSee('Wijzigingsgeschiedenis');
    $response->assertSee('Testbewerking catalogusgeschiedenis');

    // De historische gebruikersnaam is zichtbaar voor beheerders.
    $response->assertSee('Historische testbeheerder');
});

test('een gebruiker kan de oude en nieuwe waarden van een wijziging bekijken', function () {
    // Maak een gewijzigde catalogusvermelding aan in de geschiedenis.
    CatalogChangeLog::create([
        'catalog_change_batch_id' => $this->batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => null,
        'stamp_series_id' => null,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => 123,
        'action' => 'updated',
        'old_values' => [
            'catalog_number' => '100',
        ],
        'new_values' => [
            'catalog_number' => '105',
        ],
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('backend.catalogs.editions.history.show', [
            $this->catalog,
            $this->edition,
            $this->batch,
        ]));

    $response->assertOk();

    $response->assertSee('Wijzigingsdetails');
    $response->assertSee('Catalogusnummer');
    $response->assertSee('100');
    $response->assertSee('105');
    $response->assertSee('Gewijzigd');
    $response->assertSee('123');
});

test('een gebruiker kan geen geschiedenisbatch van een andere cataloguseditie bekijken', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere testcatalogus',
        'abbreviation' => 'ATC',
    ]);

    // Maak een tweede cataloguseditie aan.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    // Probeer de geschiedenisbatch via de verkeerde catalogus en editie te openen.
    $response = $this
        ->actingAs($this->user)
        ->get(route('backend.catalogs.editions.history.show', [
            $otherCatalog,
            $otherEdition,
            $this->batch,
        ]));

    // Controleer dat de toegang wordt geweigerd.
    $response->assertNotFound();
});

test('de geschiedenis blijft zichtbaar wanneer de oorspronkelijke postzegel is verwijderd', function () {
    // Maak een land aan.
    $country = Country::create([
        'name' => 'Testland verwijderde postzegel',
        'iso_code' => 'TVP',
        'entity_type' => 'country',
        'slug' => 'testland-verwijderde-postzegel',
        'is_active' => true,
    ]);

    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $country->id,
    ]);

    $stampId = $stamp->id;

    // Registreer de verwijderde catalogusvermelding.
    $log = CatalogChangeLog::create([
        'catalog_change_batch_id' => $this->batch->id,
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $stampId,
        'stamp_series_id' => null,
        'condition_id' => null,
        'record_type' => 'stamp_catalog_entry',
        'record_id' => 123,
        'action' => 'deleted',
        'old_values' => [
            'catalog_number' => 'HISTORY-100',
            'stamp_id' => $stampId,
        ],
        'new_values' => null,
    ]);

    // Verwijder de oorspronkelijke postzegel.
    $stamp->delete();

    // Controleer dat de postzegel daadwerkelijk verwijderd is.
    $this->assertDatabaseMissing('stamps', [
        'id' => $stampId,
    ]);

    // Controleer dat de geschiedenisregel behouden blijft.
    $this->assertDatabaseHas('catalog_change_logs', [
        'id' => $log->id,
        'stamp_id' => $stampId,
        'action' => 'deleted',
    ]);

    // Open de detailpagina.
    $response = $this
        ->actingAs($this->user)
        ->get(route('backend.catalogs.editions.history.show', [
            $this->catalog,
            $this->edition,
            $this->batch,
        ]));

    // Controleer de historische gegevens.
    $response->assertOk();
    $response->assertSee('Onbekende postzegel');
    $response->assertSee('HISTORY-100');
    $response->assertSee('Verwijderd');
});
