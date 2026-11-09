<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCopy;
use App\Models\StampSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een gebruiker kan een postzegel aan de persoonlijke verzameling toevoegen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.store'), [
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 3,
        ]);

    $response->assertRedirect(route('backend.stamp-copies.index'));

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamp_copies', [
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);
});

test('een gebruiker kan een verzamelexemplaar wijzigen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $newCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $stampCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamp-copies.update', $stampCopy), [
            'condition_id' => $newCondition->id,
            'quantity' => 5,
        ]);

    $response->assertRedirect(route('backend.stamp-copies.index'));

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamp_copies', [
        'id' => $stampCopy->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $newCondition->id,
        'quantity' => 5,
    ]);
});

test('een gebruiker kan een verzamelexemplaar verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamp-copies.destroy', $stampCopy));

    $response->assertRedirect(route('backend.stamp-copies.index'));

    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('stamp_copies', [
        'id' => $stampCopy->id,
    ]);

    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);
});

test('een gebruiker kan geen postzegel toevoegen met een aantal van nul', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.store'), [
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 0,
        ]);

    $response->assertSessionHasErrors(['quantity']);

    $this->assertDatabaseMissing('stamp_copies', [
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 0,
    ]);
});

test('een gebruiker kan geen postzegel toevoegen met een niet-bestaande conditie', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.store'), [
            'stamp_id' => $stamp->id,
            'condition_id' => 999999,
            'quantity' => 1,
        ]);

    $response->assertSessionHasErrors(['condition_id']);

    $this->assertDatabaseCount('stamp_copies', 0);
});

test('een bezoeker zonder account kan geen postzegel toevoegen aan de verzameling', function () {
    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->post(route('backend.stamp-copies.store'), [
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    $response->assertRedirect(route('login'));

    $this->assertDatabaseCount('stamp_copies', 0);
});

test('een gebruiker kan de persoonlijke verzameling filteren op conditie', function () {
    $user = User::factory()->create();

    // Twee verschillende condities.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Twee verschillende postzegels.
    $stampPostfris = Stamp::factory()->create([
        'name' => 'Testzegel Postfris',
    ]);

    $stampGestempeld = Stamp::factory()->create([
        'name' => 'Testzegel Gestempeld',
    ]);

    // Beide postzegels toevoegen aan de verzameling.
    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampPostfris->id,
        'condition_id' => $postfris->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampGestempeld->id,
        'condition_id' => $gestempeld->id,
        'quantity' => 1,
    ]);

    // Het verzamelingsoverzicht filteren op Postfris.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'condition_id' => $postfris->id,
        ]));

    // De pagina moet succesvol worden geladen.
    $response->assertOk();

    // De postfrisse postzegel moet zichtbaar zijn.
    $response->assertSee('Testzegel Postfris');

    // De gestempelde postzegel mag niet zichtbaar zijn.
    $response->assertDontSee('Testzegel Gestempeld');
});

test('een gebruiker kan de verzameling filteren op land en conditie', function () {
    $user = User::factory()->create();

    // Twee landen.
    $netherlands = Country::factory()->create();
    $germany = Country::factory()->create();

    // Twee condities.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Drie verschillende postzegels.
    $stampNederlandPostfris = Stamp::factory()->create([
        'country_id' => $netherlands->id,
        'name' => 'Nederland Postfris Test',
    ]);

    $stampNederlandGestempeld = Stamp::factory()->create([
        'country_id' => $netherlands->id,
        'name' => 'Nederland Gestempeld Test',
    ]);

    $stampDuitslandPostfris = Stamp::factory()->create([
        'country_id' => $germany->id,
        'name' => 'Duitsland Postfris Test',
    ]);

    // Verzamelexemplaren aanmaken.
    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampNederlandPostfris->id,
        'condition_id' => $postfris->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampNederlandGestempeld->id,
        'condition_id' => $gestempeld->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampDuitslandPostfris->id,
        'condition_id' => $postfris->id,
        'quantity' => 1,
    ]);

    // Filteren op Nederland én Postfris.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'country_id' => $netherlands->id,
            'condition_id' => $postfris->id,
        ]));

    $response->assertOk();

    // Alleen de Nederlandse postfrisse zegel mag zichtbaar zijn.
    $response->assertSee('Nederland Postfris Test');

    $response->assertDontSee('Nederland Gestempeld Test');

    $response->assertDontSee('Duitsland Postfris Test');
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet bekijken', function () {
    $owner = User::factory()->create();

    $otherUser = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->get(route('backend.stamp-copies.show', $stampCopy));

    $response->assertNotFound();
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet bewerken', function () {
    $owner = User::factory()->create();

    $otherUser = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->get(route('backend.stamp-copies.edit', $stampCopy));

    $response->assertNotFound();
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet wijzigen', function () {
    $owner = User::factory()->create();

    $otherUser = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->put(route('backend.stamp-copies.update', $stampCopy), [
            'quantity' => 5,
        ]);

    $response->assertNotFound();

    $this->assertDatabaseHas('stamp_copies', [
        'id' => $stampCopy->id,
        'user_id' => $owner->id,
        'quantity' => 1,
    ]);
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet verwijderen', function () {
    $owner = User::factory()->create();

    $otherUser = User::factory()->create();

    $stamp = Stamp::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->delete(route('backend.stamp-copies.destroy', $stampCopy));

    $response->assertNotFound();

    $this->assertDatabaseHas('stamp_copies', [
        'id' => $stampCopy->id,
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);
});

test('een gebruiker ziet alleen eigen verzamelexemplaren in het overzicht', function () {
    $user = User::factory()->create();

    $otherUser = User::factory()->create();

    $ownStamp = Stamp::factory()->create([
        'name' => 'Eigen testzegel',
    ]);

    $otherStamp = Stamp::factory()->create([
        'name' => 'Andermans testzegel',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $ownStamp->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
        'user_id' => $otherUser->id,
        'stamp_id' => $otherStamp->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index'));

    $response->assertOk();

    $response->assertSee('Eigen testzegel');

    $response->assertDontSee('Andermans testzegel');
});

test('een ongeldige cataloguseditie toont geen verzamelexemplaren', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel Ongeldige Editie',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    // Filter op een cataloguseditie die niet bestaat.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => 999999,
        ]));

    $response->assertOk();

    // Een ongeldige cataloguseditie mag geen resultaten opleveren.
    $response->assertDontSee('Testzegel Ongeldige Editie');
});

test('verzamelexemplaren worden natuurlijk gesorteerd op numeriek catalogusnummer', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus sortering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie sortering',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-sortering',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach (['1', '10', '2'] as $number) {
        $stamp = Stamp::factory()->create([
            'name' => 'Sorteertest '.$number,
        ]);

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === ['1', '2', '10'];
    });
});

test('varianten worden na de gewone postzegels van dezelfde serie gesorteerd', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie varianten',
        'issue_year' => 1940,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus varianten',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie varianten',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-varianten',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parentStamp = null;

    foreach (['332', '332v', '333', '334'] as $number) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => 'Sorteertest '.$number,
            'stamp_series_id' => $series->id,
            'parent_stamp_id' => $number === '332v'
                ? $parentStamp?->id
                : null,
            'issue_year' => 1940,
        ]);

        if ($number === '332') {
            $parentStamp = $stamp;
        }

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === ['332', '333', '334', '332v'];
    });
});

test('catalogusnummers met letterprefix worden na numerieke nummers binnen dezelfde serie gesorteerd', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie letterprefix',
        'issue_year' => 1940,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus letterprefix',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie letterprefix',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-letterprefix',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach (['745', 'E45', '755'] as $number) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => 'Sorteertest '.$number,
            'stamp_series_id' => $series->id,
            'parent_stamp_id' => null,
            'issue_year' => 1940,
        ]);

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === ['745', '755', 'E45'];
    });
});

test('losse postzegels worden op catalogusnummer tussen series gesorteerd', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie tussen losse postzegels',
        'issue_year' => 1940,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus losse postzegels',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie losse postzegels',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-losse-postzegels',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parentStamp = null;

    foreach (['332', '106', '350', '333', '332v', '107', '334'] as $number) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => 'Sorteertest '.$number,
            'stamp_series_id' => in_array($number, ['332', '333', '334', '332v'], true)
                ? $series->id
                : null,
            'parent_stamp_id' => $number === '332v'
                ? $parentStamp?->id
                : null,
            'issue_year' => 1940,
        ]);

        if ($number === '332') {
            $parentStamp = $stamp;
        }

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === ['106', '107', '332', '333', '334', '332v', '350'];
    });
});

test('onvolledige serie behoudt oorspronkelijke cataloguspositie', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie onvolledig',
        'issue_year' => 1940,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus onvolledig',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie onvolledig',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-onvolledig',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach (['332', '333', '334', '335'] as $number) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => 'Seriepostzegel '.$number,
            'stamp_series_id' => $series->id,
            'parent_stamp_id' => null,
            'issue_year' => 1940,
        ]);

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        // Alleen de laatste twee postzegels zijn in bezit.
        if (in_array($number, ['334', '335'], true)) {
            StampCopy::create([
                'user_id' => $user->id,
                'stamp_id' => $stamp->id,
                'condition_id' => $condition->id,
                'quantity' => 1,
            ]);
        }
    }

    // Een losse postzegel met catalogusnummer 333.
    $looseStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'name' => 'Losse postzegel 333',
        'stamp_series_id' => null,
        'parent_stamp_id' => null,
        'issue_year' => 1940,
    ]);

    $looseStamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '333',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $looseStamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === ['334', '335', '333'];
    });
});

test('meerdere series blijven bij elkaar tijdens sorteren op catalogusnummer', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $seriesA = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie A',
        'issue_year' => 1940,
    ]);

    $seriesB = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie B',
        'issue_year' => 1940,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere series',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie meerdere series',
        'edition_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-meerdere-series',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $parentA = null;
    $parentB = null;

    // Bewust een willekeurige invoervolgorde gebruiken.
    foreach (['300', '100', '200', '350', '301', '201', '250', '200v', '300v'] as $number) {
        $seriesId = match ($number) {
            '200', '201', '200v' => $seriesA->id,
            '300', '301', '300v' => $seriesB->id,
            default => null,
        };

        $parentId = match ($number) {
            '200v' => $parentA?->id,
            '300v' => $parentB?->id,
            default => null,
        };

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => 'Sorteertest '.$number,
            'stamp_series_id' => $seriesId,
            'parent_stamp_id' => $parentId,
            'issue_year' => 1940,
        ]);

        if ($number === '200') {
            $parentA = $stamp;
        }

        if ($number === '300') {
            $parentB = $stamp;
        }

        $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => $number,
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'sort' => 'catalog_asc',
        ]));

    $response->assertOk();

    $response->assertViewHas('stampCopies', function ($stampCopies) use ($edition) {
        $numbers = $stampCopies->getCollection()
            ->map(fn ($copy) => $copy->stamp->catalogEntries
                ->firstWhere('catalog_edition_id', $edition->id)
                ?->catalog_number)
            ->all();

        return $numbers === [
            '100',
            '200',
            '201',
            '200v',
            '250',
            '300',
            '301',
            '300v',
            '350',
        ];
    });
});
