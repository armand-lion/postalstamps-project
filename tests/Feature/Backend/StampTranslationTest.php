<?php

use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
