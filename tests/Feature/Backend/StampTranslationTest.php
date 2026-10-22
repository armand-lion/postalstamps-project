<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('een postzegel kan vijf vertalingen hebben', function () {
    $stamp = Stamp::factory()->create();

    $translations = [
        'nl' => 'Nederlandse testzegel',
        'en' => 'English test stamp',
        'de' => 'Deutsche Testbriefmarke',
        'fr' => 'Timbre de test français',
        'es' => 'Sello de prueba español',
    ];

    foreach ($translations as $locale => $name) {
        $stamp->translations()->create([
            'locale' => $locale,
            'name' => $name,
            'description' => null,
        ]);
    }

    $stamp->refresh();

    expect($stamp->translations)->toHaveCount(5);

    foreach ($translations as $locale => $name) {
        expect(
            $stamp->translations->firstWhere('locale', $locale)?->name
        )->toBe($name);
    }

});

test('de backend slaat een postzegel met vijf vertalingen op', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();

    $country = Country::factory()->create();

    $translations = [
        'nl' => [
            'name' => 'Nederlandse testzegel',
            'description' => 'Nederlandse beschrijving',
        ],
        'en' => [
            'name' => 'English test stamp',
            'description' => 'English description',
        ],
        'de' => [
            'name' => 'Deutsche Testbriefmarke',
            'description' => 'Deutsche Beschreibung',
        ],
        'fr' => [
            'name' => 'Timbre de test français',
            'description' => 'Description française',
        ],
        'es' => [
            'name' => 'Sello de prueba español',
            'description' => 'Descripción española',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamps.store'), [
            'country_id' => $country->id,
            'issue_year' => 2026,
            'translations' => $translations,
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp = Stamp::query()
        ->where('country_id', $country->id)
        ->firstOrFail();

    expect($stamp->name)->toBe('Nederlandse testzegel')
        ->and($stamp->translations)->toHaveCount(5);

    foreach ($translations as $locale => $translation) {
        $this->assertDatabaseHas('stamp_translations', [
            'stamp_id' => $stamp->id,
            'locale' => $locale,
            'name' => $translation['name'],
            'description' => $translation['description'],
        ]);
    }
});

test('de backend werkt bestaande postzegelvertalingen bij', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create([
        'name' => 'Oorspronkelijke Nederlandse naam',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Oorspronkelijke Nederlandse naam',
        'description' => 'Oorspronkelijke beschrijving',
    ]);

    $stamp->translations()->create([
        'locale' => 'en',
        'name' => 'Original English name',
        'description' => 'Original English description',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Bijgewerkte Nederlandse naam',
                    'description' => 'Bijgewerkte beschrijving',
                ],
                'en' => [
                    'name' => 'Updated English name',
                    'description' => 'Updated English description',
                ],
                'de' => [
                    'name' => 'Deutsche Testbriefmarke',
                    'description' => 'Deutsche Beschreibung',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp->refresh();

    expect($stamp->name)
        ->toBe('Bijgewerkte Nederlandse naam')
        ->and($stamp->translations)
        ->toHaveCount(3);

    foreach ([
        'nl' => 'Bijgewerkte Nederlandse naam',
        'en' => 'Updated English name',
        'de' => 'Deutsche Testbriefmarke',
    ] as $locale => $name) {
        $this->assertDatabaseHas('stamp_translations', [
            'stamp_id' => $stamp->id,
            'locale' => $locale,
            'name' => $name,
        ]);
    }
});

test('de backend behoudt bestaande vertalingen bij lege taalvelden', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Oorspronkelijke Nederlandse naam',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Oorspronkelijke Nederlandse naam',
        'description' => 'Nederlandse beschrijving',
    ]);

    $stamp->translations()->create([
        'locale' => 'en',
        'name' => 'Original English name',
        'description' => 'Original English description',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Bijgewerkte Nederlandse naam',
                    'description' => 'Nieuwe Nederlandse beschrijving',
                ],
                'en' => [
                    'name' => '',
                    'description' => '',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp->refresh();

    expect($stamp->name)
        ->toBe('Bijgewerkte Nederlandse naam')
        ->and($stamp->translations)
        ->toHaveCount(2);

    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'en',
        'name' => 'Original English name',
        'description' => 'Original English description',
    ]);

    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Bijgewerkte Nederlandse naam',
        'description' => 'Nieuwe Nederlandse beschrijving',
    ]);
});

test('een Engelstalige gebruiker kan een postzegel zonder Nederlandse naam toevoegen', function () {
    app()->setLocale('en');

    $user = User::factory()->create();
    $country = Country::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamps.store'), [
            'country_id' => $country->id,
            'issue_year' => 2026,
            'translations' => [
                'nl' => [
                    'name' => '',
                    'description' => '',
                ],
                'en' => [
                    'name' => 'English test stamp',
                    'description' => 'English test description',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp = Stamp::query()
        ->where('country_id', $country->id)
        ->firstOrFail();

    expect($stamp->name)
        ->toBe('English test stamp')
        ->and($stamp->description)
        ->toBe('English test description')
        ->and($stamp->translations)
        ->toHaveCount(1);

    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'en',
        'name' => 'English test stamp',
    ]);

    $this->assertDatabaseMissing('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
    ]);
});

test('de backend vereist een naam in de actieve applicatietaal', function () {
    app()->setLocale('en');

    $user = User::factory()->create();
    $country = Country::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamps.store'), [
            'country_id' => $country->id,
            'issue_year' => 2026,
            'translations' => [
                'nl' => [
                    'name' => 'Nederlandse testzegel',
                ],
                'en' => [
                    'name' => '',
                ],
            ],
        ]);

    $response->assertSessionHasErrors([
        'translations.en.name',
    ]);

    $this->assertDatabaseMissing('stamps', [
        'country_id' => $country->id,
        'name' => 'Nederlandse testzegel',
    ]);

    expect(
        Stamp::query()->where('country_id', $country->id)->count()
    )->toBe(0);
});

test('een Engelstalige gebruiker kan een postzegel zonder Nederlandse naam wijzigen', function () {
    app()->setLocale('en');

    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Oorspronkelijke Nederlandse naam',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Oorspronkelijke Nederlandse naam',
        'description' => 'Nederlandse beschrijving',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => '',
                    'description' => '',
                ],
                'en' => [
                    'name' => 'Updated English stamp',
                    'description' => 'Updated English description',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp->refresh();

    expect($stamp->name)
        ->toBe('Updated English stamp')
        ->and($stamp->description)
        ->toBe('Updated English description')
        ->and($stamp->translations)
        ->toHaveCount(2);

    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Oorspronkelijke Nederlandse naam',
        'description' => 'Nederlandse beschrijving',
    ]);

    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'en',
        'name' => 'Updated English stamp',
        'description' => 'Updated English description',
    ]);
});

test('een postzegel gebruikt de fallbacktaal als de actieve vertaling ontbreekt', function () {
    config()->set('app.fallback_locale', 'nl');
    app()->setLocale('en');

    $stamp = Stamp::factory()->create([
        'name' => 'Nederlandse postzegel',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse vertaling',
        'description' => 'Nederlandse beschrijving',
    ]);

    $stamp->load('translations');

    $translation = $stamp->translationWithFallback();

    expect($translation)
        ->not->toBeNull()
        ->and($translation->locale)->toBe('nl')
        ->and($translation->name)->toBe('Nederlandse vertaling')
        ->and($translation->description)->toBe('Nederlandse beschrijving');
});

test('een postzegel geeft voorrang aan de actieve applicatietaal', function () {
    app()->setLocale('en');

    $stamp = Stamp::factory()->create([
        'name' => 'Nederlandse postzegel',
    ]);

    $stamp->translations()->createMany([
        [
            'locale' => 'nl',
            'name' => 'Nederlandse vertaling',
            'description' => 'Nederlandse beschrijving',
        ],
        [
            'locale' => 'en',
            'name' => 'English translation',
            'description' => 'English description',
        ],
    ]);

    $stamp->load('translations');

    $translation = $stamp->translationWithFallback();

    expect($translation)
        ->not->toBeNull()
        ->and($translation->locale)->toBe('en')
        ->and($translation->name)->toBe('English translation')
        ->and($translation->description)->toBe('English description');
});

test('vooraf geladen postzegelvertalingen veroorzaken geen extra databasequeries', function () {
    app()->setLocale('en');

    $stamp = Stamp::factory()->create();

    $stamp->translations()->createMany([
        [
            'locale' => 'nl',
            'name' => 'Nederlandse vertaling',
        ],
        [
            'locale' => 'en',
            'name' => 'English translation',
        ],
    ]);

    $stamp->load('translations');

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $firstTranslation = $stamp->translationWithFallback();
        $secondTranslation = $stamp->translationWithFallback();

        $queries = DB::getQueryLog();

        expect($firstTranslation?->name)
            ->toBe('English translation')
            ->and($secondTranslation?->name)
            ->toBe('English translation')
            ->and($queries)
            ->toHaveCount(0);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('postzegelvertalingen worden automatisch verwijderd met de postzegel', function () {
    $stamp = Stamp::factory()->create();

    $stamp->translations()->createMany([
        [
            'locale' => 'nl',
            'name' => 'Nederlandse postzegel',
        ],
        [
            'locale' => 'en',
            'name' => 'English stamp',
        ],
    ]);

    expect($stamp->translations()->count())->toBe(2);

    $stampId = $stamp->id;

    $stamp->delete();

    $this->assertDatabaseMissing('stamps', [
        'id' => $stampId,
    ]);

    $this->assertDatabaseMissing('stamp_translations', [
        'stamp_id' => $stampId,
    ]);
});

test('de backend kan een postzegel zonder gekoppelde gegevens verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Testpostzegel voor verwijderen',
    ]);

    $stamp->translations()->createMany([
        [
            'locale' => 'nl',
            'name' => 'Nederlandse testpostzegel',
        ],
        [
            'locale' => 'en',
            'name' => 'English test stamp',
        ],
    ]);

    $stampId = $stamp->id;

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    $response->assertRedirect(route('backend.stamps.index'));

    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('stamps', [
        'id' => $stampId,
    ]);

    $this->assertDatabaseMissing('stamp_translations', [
        'stamp_id' => $stampId,
    ]);
});

test('de backend weigert een postzegel met gekoppelde exemplaren te verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Postzegel met exemplaar',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel',
    ]);

    // Maak een gekoppeld exemplaar aan.
    $stamp->copies()->create([]);

    $stampId = $stamp->id;

    // Probeer de postzegel via de backend te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    // De gebruiker wordt teruggestuurd met een foutmelding.
    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHas('error');

    // De postzegel moet blijven bestaan.
    $this->assertDatabaseHas('stamps', [
        'id' => $stampId,
    ]);

    // Het gekoppelde exemplaar moet behouden blijven.
    $this->assertDatabaseHas('stamp_copies', [
        'stamp_id' => $stampId,
    ]);

    // Ook de vertaling mag niet worden verwijderd.
    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stampId,
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel',
    ]);
});

test('de backend weigert een postzegel met gekoppelde afbeeldingen te verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Postzegel met afbeelding',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel met afbeelding',
    ]);

    // Maak een gekoppelde testafbeelding aan.
    $image = $stamp->images()->create([
        'path' => 'stamps/test-afbeelding.jpg',
        'image_type' => 'front',
        'is_primary' => true,
    ]);

    // Probeer de postzegel via de backend te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    // De backend moet de verwijdering weigeren.
    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHas('error');

    // De postzegel moet behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);

    // De gekoppelde afbeelding moet behouden blijven.
    $this->assertDatabaseHas('stamp_images', [
        'id' => $image->id,
        'stamp_id' => $stamp->id,
        'path' => 'stamps/test-afbeelding.jpg',
    ]);

    // Ook de vertaling moet behouden blijven.
    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel met afbeelding',
    ]);
});

test('de backend weigert een postzegel met catalogusvermeldingen te verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Postzegel met catalogusvermelding',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse cataloguspostzegel',
    ]);

    // Maak een catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus',
    ]);

    // Maak een editie van deze catalogus aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie',
        'edition_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-001',
    ]);

    // Probeer de postzegel te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    // De backend moet de verwijdering blokkeren.
    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHas('error');

    // De postzegel moet behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);

    // De catalogusvermelding moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $entry->id,
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-001',
    ]);

    // De cataloguseditie moet behouden blijven.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $edition->id,
        'catalog_id' => $catalog->id,
    ]);

    // De catalogus moet behouden blijven.
    $this->assertDatabaseHas('catalogs', [
        'id' => $catalog->id,
        'name' => 'Testcatalogus',
    ]);

    // Ook de vertaling moet behouden blijven.
    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Nederlandse cataloguspostzegel',
    ]);
});

test('de backend weigert een postzegel met gekoppelde waarderingen te verwijderen', function () {
    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'name' => 'Postzegel met waardering',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel met waardering',
    ]);

    // Maak een gekoppelde waardering aan.
    $valuation = $stamp->valuations()->create([
        'valuation_date' => '2026-10-22',
        'value' => 125.50,
        'currency' => 'EUR',
        'source' => 'Testcatalogus',
    ]);

    // Probeer de postzegel via de backend te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    // De backend moet de verwijdering blokkeren.
    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHas('error');

    // De postzegel moet behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);

    // De gekoppelde waardering moet behouden blijven.
    $this->assertDatabaseHas('stamp_valuations', [
        'id' => $valuation->id,
        'stamp_id' => $stamp->id,
        'value' => 125.50,
        'currency' => 'EUR',
    ]);

    // Controleer de datum via de bestaande Laravel date-cast.
    expect($valuation->fresh()->valuation_date->toDateString())
        ->toBe('2026-10-22');

    // Ook de vertaling moet behouden blijven.
    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Nederlandse postzegel met waardering',
    ]);
});

test('de backend weigert een hoofdpostzegel met gekoppelde varianten te verwijderen', function () {
    $user = User::factory()->create();

    // Maak een hoofdpostzegel aan.
    $stamp = Stamp::factory()->create([
        'name' => 'Hoofdpostzegel',
    ]);

    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederlandse hoofdpostzegel',
    ]);

    // Maak een variant van de hoofdpostzegel aan.
    $variant = Stamp::factory()->create([
        'name' => 'Variant van hoofdpostzegel',
        'parent_stamp_id' => $stamp->id,
    ]);

    // Probeer de hoofdpostzegel te verwijderen.
    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamps.destroy', $stamp));

    // De backend moet de verwijdering blokkeren.
    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHas('error');

    // De hoofdpostzegel moet behouden blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
        'name' => 'Hoofdpostzegel',
    ]);

    // De variant moet behouden blijven én gekoppeld blijven.
    $this->assertDatabaseHas('stamps', [
        'id' => $variant->id,
        'parent_stamp_id' => $stamp->id,
        'name' => 'Variant van hoofdpostzegel',
    ]);

    // Ook de vertaling moet behouden blijven.
    $this->assertDatabaseHas('stamp_translations', [
        'stamp_id' => $stamp->id,
        'locale' => 'nl',
        'name' => 'Nederlandse hoofdpostzegel',
    ]);
});
