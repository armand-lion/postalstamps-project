<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('niet-ingelogde bezoekers worden doorgestuurd naar de inlogpagina', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijking',
    ]);

    // Open de vergelijkingspagina zonder ingelogde gebruiker.
    $response = $this->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de bezoeker wordt doorgestuurd naar de inlogpagina.
    $response->assertRedirect(route('login'));
});

test('een ingelogde beheerder kan de vergelijkingspagina openen', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijking beheerder',
    ]);

    // Open de vergelijkingspagina als ingelogde beheerder.
    $response = $this->actingAs($admin)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de vergelijkingspagina succesvol wordt weergegeven.
    $response->assertOk();
});

test('een ingelogde gewone gebruiker kan de vergelijkingspagina openen', function () {
    // Maak een gewone gebruiker zonder beheerdersrechten aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijking gewone gebruiker',
    ]);

    // Open de vergelijkingspagina als ingelogde gewone gebruiker.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de vergelijkingspagina succesvol wordt weergegeven.
    $response->assertOk();
});

test('een niet-bestaande catalogus resulteert in een 404-melding', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Bepaal een niet-bestaand catalogus-ID.
    $nonExistingCatalogId = (Catalog::max('id') ?? 0) + 1;

    // Open de vergelijkingspagina van een niet-bestaande catalogus.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $nonExistingCatalogId)
    );

    // Controleer of Laravel een HTTP 404-melding retourneert.
    $response->assertNotFound();
});

test('de controller gebruikt de juiste Blade-view voor de vergelijkingspagina', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijkingsview',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de controller de juiste Blade-view gebruikt.
    $response->assertViewIs('backend.catalog-editions.compare');
});

test('de controller geeft de juiste catalogus door aan de vergelijkingspagina', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak de geselecteerde catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geselecteerde vergelijking',
    ]);

    // Maak een andere catalogus aan.
    Catalog::create([
        'name' => 'Testcatalogus andere vergelijking',
    ]);

    // Open de vergelijkingspagina van de geselecteerde catalogus.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de juiste catalogus aan de view wordt doorgegeven.
    $response->assertViewHas('catalog', function ($viewCatalog) use ($catalog) {
        return $viewCatalog instanceof Catalog
            && $viewCatalog->is($catalog)
            && $viewCatalog->name === $catalog->name;
    });
});

test('de controller geeft uitsluitend edities van de geselecteerde catalogus door', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak de geselecteerde catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus eigen edities',
    ]);

    // Maak een andere catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Testcatalogus andere edities',
    ]);

    // Maak twee edities van de geselecteerde catalogus aan.
    $firstEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    $secondEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak een editie van de andere catalogus aan.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Andere editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Open de vergelijkingspagina van de geselecteerde catalogus.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Haal de doorgegeven catalogusedities op.
    $editions = $response->viewData('editions');

    // Controleer het aantal beschikbare edities.
    expect($editions)->toHaveCount(2);

    // Controleer of beide bijbehorende edities aanwezig zijn.
    expect($editions->contains('id', $firstEdition->id))->toBeTrue();
    expect($editions->contains('id', $secondEdition->id))->toBeTrue();

    // Controleer of de editie van de andere catalogus ontbreekt.
    expect($editions->contains('id', $otherEdition->id))->toBeFalse();

    // Controleer of alle opgehaalde edities bij de geselecteerde catalogus horen.
    expect($editions->every(
        fn (CatalogEdition $edition) => $edition->catalog_id === $catalog->id
    ))->toBeTrue();
});

test('de controller sorteert catalogusedities aflopend op editiejaar', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus sortering editiejaar',
    ]);

    // Maak catalogusedities bewust in een afwijkende volgorde aan.
    $edition2025 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    $edition2024 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2024',
        'edition_year' => 2024,
        'currency' => 'EUR',
    ]);

    $edition2026 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Haal de doorgegeven catalogusedities op.
    $editions = $response->viewData('editions');

    // Controleer het aantal beschikbare edities.
    expect($editions)->toHaveCount(3);

    // Controleer de aflopende volgorde van de editiejaren.
    expect($editions->pluck('edition_year')->all())->toBe([
        2026,
        2025,
        2024,
    ]);

    // Controleer ook de volgorde van de cataloguseditie-ID's.
    expect($editions->pluck('id')->all())->toBe([
        $edition2026->id,
        $edition2025->id,
        $edition2024->id,
    ]);
});

test('de controller sorteert catalogusedities met hetzelfde editiejaar alfabetisch op editienaam', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus alfabetische sortering',
    ]);

    // Maak catalogusedities bewust in een niet-alfabetische volgorde aan.
    $editionC = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie C',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $editionA = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie A',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $editionB = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie B',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak een oudere editie aan die alfabetisch eerder zou verschijnen.
    $olderEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Haal de doorgegeven catalogusedities op.
    $editions = $response->viewData('editions');

    // Controleer het aantal beschikbare edities.
    expect($editions)->toHaveCount(4);

    // Controleer de volgorde van de editiejaren.
    expect($editions->pluck('edition_year')->all())->toBe([
        2026,
        2026,
        2026,
        2025,
    ]);

    // Controleer de alfabetische sortering binnen hetzelfde editiejaar.
    expect($editions->pluck('edition_name')->all())->toBe([
        'Editie A',
        'Editie B',
        'Editie C',
        'Editie 2025',
    ]);

    // Controleer de volgorde van de cataloguseditie-ID's.
    expect($editions->pluck('id')->all())->toBe([
        $editionA->id,
        $editionB->id,
        $editionC->id,
        $olderEdition->id,
    ]);
});

test('de vergelijkingspagina toont de juiste melding bij een catalogus zonder edities', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een catalogus zonder catalogusedities aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus zonder edities',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Controleer of er geen catalogusedities beschikbaar zijn.
    expect($response->viewData('editions'))->toBeEmpty();

    // Controleer of de juiste melding wordt weergegeven.
    $response->assertSee(
        __('Onvoldoende catalogusedities beschikbaar')
    );

    // Controleer of de bijbehorende toelichting wordt weergegeven.
    $response->assertSee(
        __('Er zijn minimaal twee catalogusedities nodig om een vergelijking uit te voeren.')
    );
});

test('de vergelijkingspagina toont de juiste melding bij een catalogus met één editie', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met één editie',
    ]);

    // Maak precies één cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Haal de doorgegeven catalogusedities op.
    $editions = $response->viewData('editions');

    // Controleer of precies één editie beschikbaar is.
    expect($editions)->toHaveCount(1);

    // Controleer of de juiste editie aanwezig is.
    expect($editions->first()->id)->toBe($edition->id);

    // Controleer of de melding over onvoldoende edities zichtbaar is.
    $response->assertSee(
        __('Onvoldoende catalogusedities beschikbaar')
    );

    // Controleer of de toelichting zichtbaar is.
    $response->assertSee(
        __('Er zijn minimaal twee catalogusedities nodig om een vergelijking uit te voeren.')
    );
});

test('de vergelijkingspagina toont de selectiesectie bij minimaal twee catalogusedities', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met meerdere edities',
    ]);

    // Maak de eerste cataloguseditie aan.
    $firstEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de tweede cataloguseditie aan.
    $secondEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Open de vergelijkingspagina.
    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de edities aan de view worden doorgegeven.
    $response->assertViewHas('editions');

    // Haal de beschikbare catalogusedities op.
    $editions = $response->viewData('editions');

    // Controleer of beide edities beschikbaar zijn.
    expect($editions)->toHaveCount(2);

    // Controleer of de juiste edities zijn opgehaald.
    expect($editions->contains('id', $firstEdition->id))->toBeTrue();
    expect($editions->contains('id', $secondEdition->id))->toBeTrue();

    // Controleer of de selectiesectie zichtbaar is.
    $response->assertSee(
        __('Catalogusedities selecteren')
    );

    // Controleer of de toelichting bij de selectiesectie zichtbaar is.
    $response->assertSee(
        __('Selecteer twee verschillende edities om de cataloguswaarden te vergelijken.')
    );

    // Controleer of de melding over onvoldoende edities niet verschijnt.
    $response->assertDontSee(
        __('Onvoldoende catalogusedities beschikbaar')
    );
});

test('de vergelijking vereist een vorige cataloguseditie', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus validatie vorige editie',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Verstuur een vergelijking zonder vorige cataloguseditie.
    $response = $this->actingAs($user)
        ->from(route('backend.catalogs.editions.compare', $catalog))
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'current_edition_id' => $edition->id,
        ]));

    // Controleer of Laravel terugstuurt naar de vergelijkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de vorige cataloguseditie verplicht is.
    $response->assertSessionHasErrors('previous_edition_id');

    // Controleer of de huidige cataloguseditie geldig is.
    $response->assertSessionDoesntHaveErrors('current_edition_id');
});

test('de vergelijking vereist een huidige cataloguseditie', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus validatie huidige editie',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Verstuur een vergelijking zonder huidige cataloguseditie.
    $response = $this->actingAs($user)
        ->from(route('backend.catalogs.editions.compare', $catalog))
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $edition->id,
        ]));

    // Controleer of Laravel terugstuurt naar de vergelijkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de huidige cataloguseditie verplicht is.
    $response->assertSessionHasErrors('current_edition_id');

    // Controleer of de vorige cataloguseditie geldig is.
    $response->assertSessionDoesntHaveErrors('previous_edition_id');
});

test('de vergelijking weigert een niet-bestaande cataloguseditie', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus niet-bestaande editie',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Verstuur een vergelijking met een niet-bestaande vorige editie.
    $response = $this->actingAs($user)
        ->from(route('backend.catalogs.editions.compare', $catalog))
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => 999999,
            'current_edition_id' => $edition->id,
        ]));

    // Controleer of Laravel terugstuurt naar de vergelijkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de niet-bestaande editie wordt afgewezen.
    $response->assertSessionHasErrors('previous_edition_id');

    // Controleer of de bestaande editie geldig is.
    $response->assertSessionDoesntHaveErrors('current_edition_id');
});

test('de vergelijking weigert een cataloguseditie uit een andere catalogus', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak twee verschillende catalogi aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijking',
    ]);

    $otherCatalog = Catalog::create([
        'name' => 'Andere testcatalogus',
    ]);

    // Maak een cataloguseditie aan voor de geselecteerde catalogus.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak een cataloguseditie aan voor de andere catalogus.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Probeer edities uit verschillende catalogi te vergelijken.
    $response = $this->actingAs($user)
        ->from(route('backend.catalogs.editions.compare', $catalog))
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $otherEdition->id,
            'current_edition_id' => $edition->id,
        ]));

    // Controleer of Laravel terugstuurt naar de vergelijkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of de editie uit de andere catalogus wordt afgewezen.
    $response->assertSessionHasErrors('previous_edition_id');

    // Controleer of de editie uit de geselecteerde catalogus geldig is.
    $response->assertSessionDoesntHaveErrors('current_edition_id');
});

test('de vergelijking weigert twee identieke catalogusedities', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus identieke edities',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Probeer dezelfde editie met zichzelf te vergelijken.
    $response = $this->actingAs($user)
        ->from(route('backend.catalogs.editions.compare', $catalog))
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $edition->id,
            'current_edition_id' => $edition->id,
        ]));

    // Controleer of Laravel terugstuurt naar de vergelijkingspagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.compare', $catalog)
    );

    // Controleer of twee identieke edities worden afgewezen.
    $response->assertSessionHasErrors('current_edition_id');

    // Controleer of de vorige cataloguseditie op zichzelf geldig is.
    $response->assertSessionDoesntHaveErrors('previous_edition_id');
});

test('de vergelijking accepteert twee geldige catalogusedities', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geldige vergelijking',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Verstuur een vergelijking met twee geldige catalogusedities.
    $response = $this->actingAs($user)
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $previousEdition->id,
            'current_edition_id' => $currentEdition->id,
        ]));

    // Controleer of de vergelijkingspagina succesvol wordt geopend.
    $response->assertOk();

    // Controleer of de juiste Blade-view wordt gebruikt.
    $response->assertViewIs('backend.catalog-editions.compare');

    // Controleer of beide catalogusedities beschikbaar zijn.
    $response->assertViewHas('editions', function ($editions) use ($previousEdition, $currentEdition) {
        return $editions->contains('id', $previousEdition->id)
            && $editions->contains('id', $currentEdition->id);
    });

    // Controleer of er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();
});

test('de vergelijkingspagina ontvangt een lege resultatenlijst zonder selectie', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege vergelijkingsresultaten',
    ]);

    // Open de vergelijkingspagina zonder geselecteerde edities.
    $response = $this->actingAs($user)
        ->get(route('backend.catalogs.editions.compare', $catalog));

    // Controleer of de pagina succesvol wordt geopend.
    $response->assertOk();

    // Controleer of de vergelijkingsresultaten een lege array bevatten.
    $response->assertViewHas('comparisonResults', []);
});

test('de controller geeft berekende vergelijkingsresultaten door aan de view', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus vergelijkingsresultaten',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Vraag een vergelijking tussen beide edities aan.
    $response = $this->actingAs($user)
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $previousEdition->id,
            'current_edition_id' => $currentEdition->id,
        ]));

    // Controleer of de pagina succesvol wordt geopend.
    $response->assertOk();

    // Controleer of de resultaten aan de view worden doorgegeven.
    $response->assertViewHas('comparisonResults', []);

    // Controleer of de juiste Blade-view wordt gebruikt.
    $response->assertViewIs('backend.catalog-editions.compare');
});

test('de controller geeft een berekende waardestijging door aan de view', function () {
    // Maak een ingelogde gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus waardestijging controller',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-005',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-005',
    ]);

    // Registreer de oorspronkelijke cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de verhoogde cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Vraag de vergelijking tussen beide edities aan.
    $response = $this->actingAs($user)
        ->get(route('backend.catalogs.editions.compare', [
            'catalog' => $catalog,
            'previous_edition_id' => $previousEdition->id,
            'current_edition_id' => $currentEdition->id,
        ]));

    // Controleer of de vergelijkingspagina succesvol wordt geopend.
    $response->assertOk();

    // Controleer de berekende resultaten die de view ontvangt.
    $response->assertViewHas('comparisonResults', function ($results) use ($stamp, $condition) {
        // Er moet precies één postzegel zijn gevonden.
        if (count($results) !== 1) {
            return false;
        }

        $result = $results[0];

        // Controleer de postzegel en de vergelijkingsstatus.
        if (
            $result['stamp_id'] !== $stamp->id ||
            $result['status'] !== 'existing' ||
            count($result['conditions']) !== 1
        ) {
            return false;
        }

        $value = $result['conditions'][0];

        // Controleer de cataloguswaarden en de berekende stijging.
        return $value['condition_id'] === $condition->id
            && $value['previous_value'] === 10.00
            && $value['current_value'] === 15.00
            && $value['previous_currency'] === 'EUR'
            && $value['current_currency'] === 'EUR'
            && $value['absolute_change'] === 5.00
            && $value['percentage_change'] === 50.00
            && $value['status'] === 'increased';
    });
});
