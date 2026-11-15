<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een niet-ingelogde gebruiker kan geen cataloguswaarde goedkeuren', function () {
    $this->patch(
        route('backend.stamp-catalog-values.review', [
            'stampCatalogValue' => 999999,
        ])
    )->assertRedirect(route('login'));
});

test('een gewone gebruiker kan geen cataloguswaarde goedkeuren', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus autorisatie',
        'abbreviation' => 'TCA',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '123a',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $catalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    $this->actingAs($user)
        ->patch(
            route('backend.stamp-catalog-values.review', $catalogValue)
        )
        ->assertForbidden();

    expect($catalogValue->fresh()->needs_review)->toBeTrue();

    $otherCondition = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $otherCatalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $otherCondition->id,
        'catalog_value' => 25.00,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->patch(
            route('backend.stamp-catalog-values.review', $catalogValue)
        )
        ->assertRedirect();

    $reviewedValue = $catalogValue->fresh();

    expect($reviewedValue->needs_review)->toBeFalse()
        ->and($reviewedValue->catalog_value)->toBe('12.50')
        ->and($reviewedValue->currency)->toBe('EUR');

    expect($otherCatalogValue->fresh()->needs_review)->toBeTrue();
});

test('een beheerder passeert de autorisatiecontrole', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->patch(
            route('backend.stamp-catalog-values.review', [
                'stampCatalogValue' => 999999,
            ])
        )
        ->assertNotFound();
});

test('een gewijzigde cataloguswaarde moet opnieuw gecontroleerd worden', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus controlemarkering',
        'abbreviation' => 'TCC',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '123a',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $catalogValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Een gewijzigd bedrag moet opnieuw gecontroleerd worden.
    $catalogValue->update([
        'catalog_value' => 12.50,
    ]);

    expect($catalogValue->fresh()->needs_review)->toBeTrue();

    // Markeer de waarde opnieuw als gecontroleerd.
    $catalogValue->update([
        'needs_review' => false,
    ]);

    expect($catalogValue->fresh()->needs_review)->toBeFalse();

    // Hetzelfde bedrag opnieuw opslaan mag de status niet wijzigen.
    $catalogValue->update([
        'catalog_value' => 12.50,
    ]);

    expect($catalogValue->fresh()->needs_review)->toBeFalse();
});
