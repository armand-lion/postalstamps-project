<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\Theme;
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

test('de backend koppelt meerdere thema’s aan een nieuwe postzegel', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $country = Country::factory()->create();

    $theme1 = Theme::create([
        'name' => 'Testthema natuur',
        'slug' => 'testthema-natuur',
    ]);

    $theme2 = Theme::create([
        'name' => 'Testthema dieren',
        'slug' => 'testthema-dieren',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamps.store'), [
            'country_id' => $country->id,
            'issue_year' => 2026,
            'theme_ids' => [
                $theme1->id,
                $theme2->id,
            ],
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met twee thema’s',
                    'description' => null,
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp = Stamp::query()
        ->where('country_id', $country->id)
        ->firstOrFail();

    $this->assertDatabaseHas('stamp_themes', [
        'stamp_id' => $stamp->id,
        'theme_id' => $theme1->id,
    ]);

    $this->assertDatabaseHas('stamp_themes', [
        'stamp_id' => $stamp->id,
        'theme_id' => $theme2->id,
    ]);

    expect($stamp->themes)->toHaveCount(2);
});

test('de backend kan themakoppelingen van een postzegel wijzigen en verwijderen', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $theme1 = Theme::create([
        'name' => 'Testthema natuur',
        'slug' => 'testthema-natuur',
    ]);

    $theme2 = Theme::create([
        'name' => 'Testthema dieren',
        'slug' => 'testthema-dieren',
    ]);

    $theme3 = Theme::create([
        'name' => 'Testthema bloemen',
        'slug' => 'testthema-bloemen',
    ]);

    // Koppel aanvankelijk twee thema's.
    $stamp->themes()->sync([
        $theme1->id,
        $theme2->id,
    ]);

    $data = [
        'country_id' => $stamp->country_id,
        'issue_year' => $stamp->issue_year,
        'translations' => [
            'nl' => [
                'name' => 'Postzegel met thema’s',
                'description' => null,
            ],
        ],
    ];

    // Verwijder thema 1, behoud thema 2 en voeg thema 3 toe.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), array_merge(
            $data,
            [
                'theme_ids' => [
                    $theme2->id,
                    $theme3->id,
                ],
            ]
        ));

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    expect(
        $stamp->fresh()->themes()->pluck('themes.id')->sort()->values()->all()
    )->toBe([
        $theme2->id,
        $theme3->id,
    ]);

    // Verstuur geen theme_ids: alle selectievakjes zijn uitgevinkt.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), $data);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    expect($stamp->fresh()->themes)->toHaveCount(0);
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

test('de backend slaat een officieel catalogusnummer bij een nieuwe postzegel op', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $country = Country::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Nederlandse Vereniging van Postzegelhandelaren',
        'abbreviation' => 'NVPH',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'NVPH 2026',
        'edition_year' => 2026,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamps.store'), [
            'country_id' => $country->id,
            'issue_year' => 2026,
            'catalog_edition_id' => $edition->id,
            'catalog_number' => 'AU 10',
            'translations' => [
                'nl' => [
                    'name' => 'Testzegel met catalogusnummer',
                    'description' => 'Testbeschrijving',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $stamp = Stamp::query()
        ->where('name', 'Testzegel met catalogusnummer')
        ->firstOrFail();

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'AU 10',
    ]);

    expect($stamp->catalogEntries)->toHaveCount(1);
});
test('de backend voegt een tweede cataloguskoppeling toe zonder de eerste te verwijderen', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Nederlandse Vereniging van Postzegelhandelaren',
        'abbreviation' => 'NVPH',
    ]);

    $firstEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'NVPH 2026',
        'edition_year' => 2026,
    ]);

    $secondEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'NVPH 2027',
        'edition_year' => 2027,
    ]);

    // Bestaande cataloguskoppeling.
    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $firstEdition->id,
        'catalog_number' => '123a',
    ]);

    // Voeg via de backend een tweede cataloguskoppeling toe.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'catalog_edition_id' => $secondEdition->id,
            'catalog_number' => 'AU 10',
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met twee catalogusnummers',
                    'description' => null,
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    // De oorspronkelijke koppeling moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $firstEdition->id,
        'catalog_number' => '123a',
    ]);

    // De nieuwe koppeling moet zijn toegevoegd.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $secondEdition->id,
        'catalog_number' => 'AU 10',
    ]);

    expect($stamp->fresh()->catalogEntries)->toHaveCount(2);
});

test('de backend werkt een bestaande cataloguskoppeling bij zonder duplicaat', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Nederlandse Vereniging van Postzegelhandelaren',
        'abbreviation' => 'NVPH',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'NVPH 2026',
        'edition_year' => 2026,
    ]);

    // De postzegel heeft al een cataloguskoppeling.
    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '123a',
    ]);

    // Wijzig het catalogusnummer binnen dezelfde cataloguseditie.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'catalog_edition_id' => $edition->id,
            'catalog_number' => 'AU 10',
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met catalogusnummer',
                    'description' => null,
                ],
            ],
        ]);

    // De wijziging moet succesvol worden opgeslagen.
    $response->assertSessionHasNoErrors();

    $response->assertRedirect(
        route('backend.stamps.index')
    );

    // Het nieuwe catalogusnummer moet zijn opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'AU 10',
    ]);

    // Het oude catalogusnummer mag niet meer bestaan.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '123a',
    ]);

    // Er mag geen tweede koppeling zijn aangemaakt.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(1);
});

test('de backend koppelt een variant aan een hoofdpostzegel', function () {
    app()->setLocale('nl');
    $country = Country::factory()->create();

    $parentStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('backend.stamps.store'), [
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
        'issue_year' => 2026,
        'translations' => [
            'nl' => [
                'name' => 'Testvariant 2026',
                'description' => 'Variant van een bestaande postzegel.',
            ],
        ],
    ]);

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamps', [
        'name' => 'Testvariant 2026',
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
    ]);

    expect($parentStamp->variants()->count())->toBe(1);
});

test('de backend weigert een hoofdpostzegel uit een ander land', function () {
    app()->setLocale('nl');

    $netherlands = Country::factory()->create();
    $germany = Country::factory()->create();

    $parentStamp = Stamp::factory()->create([
        'country_id' => $netherlands->id,
        'parent_stamp_id' => null,
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('backend.stamps.store'), [
        'country_id' => $germany->id,
        'parent_stamp_id' => $parentStamp->id,
        'issue_year' => 2026,
        'translations' => [
            'nl' => [
                'name' => 'Ongeldige variant',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['parent_stamp_id']);

    $this->assertDatabaseMissing('stamps', [
        'name' => 'Ongeldige variant',
        'parent_stamp_id' => $parentStamp->id,
    ]);
});

test('de backend weigert een variant als hoofdpostzegel', function () {
    app()->setLocale('nl');

    $country = Country::factory()->create();

    $parentStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    $existingVariant = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('backend.stamps.store'), [
        'country_id' => $country->id,
        'parent_stamp_id' => $existingVariant->id,
        'issue_year' => 2026,
        'translations' => [
            'nl' => [
                'name' => 'Ongeldige geneste variant',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['parent_stamp_id']);

    $this->assertDatabaseMissing('stamps', [
        'name' => 'Ongeldige geneste variant',
        'parent_stamp_id' => $existingVariant->id,
    ]);
});

test('de backend weigert een hoofdpostzegel met varianten om te zetten naar een variant', function () {
    app()->setLocale('nl');

    $country = Country::factory()->create();

    // Een zelfstandige hoofdpostzegel.
    $parentStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    // Deze hoofdpostzegel heeft al een variant.
    Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
    ]);

    // Een andere zelfstandige hoofdpostzegel.
    $otherParentStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    $this->actingAs(User::factory()->create());

    // Probeer de eerste hoofdpostzegel zelf een variant te maken.
    $response = $this->put(
        route('backend.stamps.update', $parentStamp),
        [
            'country_id' => $country->id,
            'parent_stamp_id' => $otherParentStamp->id,
            'issue_year' => 2026,
            'translations' => [
                'nl' => [
                    'name' => 'Gewijzigde hoofdpostzegel',
                ],
            ],
        ]
    );

    // Laravel moet deze wijziging weigeren.
    $response->assertSessionHasErrors(['parent_stamp_id']);

    // De oorspronkelijke hoofdpostzegel blijft zelfstandig.
    $this->assertDatabaseHas('stamps', [
        'id' => $parentStamp->id,
        'parent_stamp_id' => null,
    ]);

    // De bestaande variant blijft gekoppeld.
    expect($parentStamp->variants()->count())->toBe(1);
});

test('de backend kan een bestaande variant aan een andere hoofdpostzegel koppelen', function () {
    app()->setLocale('nl');

    $country = Country::factory()->create();

    // Twee zelfstandige hoofdpostzegels uit hetzelfde land.
    $firstParent = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    $secondParent = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    // De variant is oorspronkelijk gekoppeld aan de eerste hoofdpostzegel.
    $variant = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => $firstParent->id,
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->put(
        route('backend.stamps.update', $variant),
        [
            'country_id' => $country->id,
            'parent_stamp_id' => $secondParent->id,
            'issue_year' => 2026,
            'translations' => [
                'nl' => [
                    'name' => 'Variant met gewijzigde hoofdpostzegel',
                ],
            ],
        ]
    );

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    // De variant moet nu gekoppeld zijn aan de tweede hoofdpostzegel.
    $this->assertDatabaseHas('stamps', [
        'id' => $variant->id,
        'parent_stamp_id' => $secondParent->id,
    ]);

    // De eerste hoofdpostzegel heeft geen varianten meer.
    expect($firstParent->variants()->count())->toBe(0);

    // De tweede hoofdpostzegel heeft de variant overgenomen.
    expect($secondParent->variants()->count())->toBe(1);
});

test('de backend kan een bestaande variant zelfstandig maken', function () {
    app()->setLocale('nl');

    $country = Country::factory()->create();

    $parentStamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => null,
    ]);

    $variant = Stamp::factory()->create([
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->put(
        route('backend.stamps.update', $variant),
        [
            'country_id' => $country->id,
            'parent_stamp_id' => '',
            'issue_year' => 2026,
            'translations' => [
                'nl' => [
                    'name' => 'Zelfstandige postzegel',
                ],
            ],
        ]
    );

    $response->assertRedirect(route('backend.stamps.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamps', [
        'id' => $variant->id,
        'parent_stamp_id' => null,
    ]);

    expect($parentStamp->variants()->count())->toBe(0);
});
