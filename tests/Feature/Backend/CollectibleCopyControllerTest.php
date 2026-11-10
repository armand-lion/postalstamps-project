<?php

use App\Models\Collectible;
use App\Models\CollectibleType;
use App\Models\Condition;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een gebruiker kan een verzamelobject aan de persoonlijke verzameling toevoegen', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.collectible-copies.store'), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 3,
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('collectible_copies', [
        'user_id' => $user->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
        'is_variant' => false,
    ]);
});

test('een gebruiker kan een verzamelexemplaar van een verzamelobject wijzigen', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

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

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.collectible-copies.update', $collectibleCopy), [
            'collectible_id' => $collectible->id,
            'condition_id' => $newCondition->id,
            'quantity' => 5,
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $user->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $newCondition->id,
        'quantity' => 5,
    ]);
});

test('een gebruiker kan geen verzamelexemplaar toevoegen met een aantal van nul', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.collectible-copies.store'), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 0,
        ]);

    $response->assertSessionHasErrors(['quantity']);

    $this->assertDatabaseMissing('collectible_copies', [
        'user_id' => $user->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 0,
    ]);

    $this->assertDatabaseCount('collectible_copies', 0);
});

test('een gebruiker kan geen verzamelexemplaar toevoegen met een niet-bestaande conditie', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.collectible-copies.store'), [
            'collectible_id' => $collectible->id,
            'condition_id' => 999999,
            'quantity' => 1,
        ]);

    $response->assertSessionHasErrors(['condition_id']);

    $this->assertDatabaseMissing('collectible_copies', [
        'user_id' => $user->id,
        'collectible_id' => $collectible->id,
        'condition_id' => 999999,
    ]);

    $this->assertDatabaseCount('collectible_copies', 0);
});

test('een bezoeker zonder account kan geen verzamelobject toevoegen aan de verzameling', function () {
    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->post(route('backend.collectible-copies.store'), [
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response->assertRedirect(route('login'));

    $this->assertDatabaseCount('collectible_copies', 0);
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet wijzigen', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $owner->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->put(route('backend.collectible-copies.update', $collectibleCopy), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 5,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $owner->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet bewerken', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $owner->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->get(route('backend.collectible-copies.edit', $collectibleCopy));

    $response->assertForbidden();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $owner->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);
});

test('een gebruiker ziet alleen eigen verzamelexemplaren in het overzicht', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $ownCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $otherCopy = $otherUser->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.collectible-copies.index'));

    $response->assertOk();

    $response->assertViewHas('collectibleCopies', function ($copies) use ($ownCopy, $otherCopy) {
        return $copies->contains('id', $ownCopy->id)
            && ! $copies->contains('id', $otherCopy->id);
    });
});

test('een gebruiker kan een verzamelexemplaar met meertalige variantbeschrijvingen toevoegen', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.collectible-copies.store'), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
            'is_variant' => 1,
            'translations' => [
                'nl' => [
                    'variant_description' => 'Bijzondere kleurafwijking',
                ],
                'en' => [
                    'variant_description' => 'Special colour variation',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));
    $response->assertSessionHasNoErrors();

    $collectibleCopy = $user->collectibleCopies()->firstOrFail();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $user->id,
        'collectible_id' => $collectible->id,
        'is_variant' => true,
    ]);

    $this->assertDatabaseHas('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
        'variant_description' => 'Bijzondere kleurafwijking',
    ]);

    $this->assertDatabaseHas('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
        'variant_description' => 'Special colour variation',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 2);
});

test('een gebruiker kan meertalige variantbeschrijvingen wijzigen', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
        'is_variant' => true,
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'nl',
        'variant_description' => 'Oorspronkelijke kleurafwijking',
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'en',
        'variant_description' => 'Original colour variation',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.collectible-copies.update', $collectibleCopy), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
            'is_variant' => 1,
            'translations' => [
                'nl' => [
                    'variant_description' => 'Gewijzigde kleurafwijking',
                ],
                'en' => [
                    'variant_description' => 'Updated colour variation',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
        'variant_description' => 'Gewijzigde kleurafwijking',
    ]);

    $this->assertDatabaseHas('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
        'variant_description' => 'Updated colour variation',
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
        'variant_description' => 'Oorspronkelijke kleurafwijking',
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
        'variant_description' => 'Original colour variation',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 2);
});

test('variantbeschrijvingen worden verwijderd wanneer een verzamelexemplaar geen variant meer is', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
        'is_variant' => true,
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'nl',
        'variant_description' => 'Bijzondere kleurafwijking',
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'en',
        'variant_description' => 'Special colour variation',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.collectible-copies.update', $collectibleCopy), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
            'is_variant' => 0,
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $user->id,
        'is_variant' => false,
        'quantity' => 1,
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 0);
});

test('een lege variantbeschrijving verwijdert alleen de betreffende vertaling', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
        'is_variant' => true,
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'nl',
        'variant_description' => 'Bijzondere kleurafwijking',
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'en',
        'variant_description' => 'Special colour variation',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.collectible-copies.update', $collectibleCopy), [
            'collectible_id' => $collectible->id,
            'condition_id' => $condition->id,
            'quantity' => 1,
            'is_variant' => 1,
            'translations' => [
                'nl' => [
                    'variant_description' => 'Bijzondere kleurafwijking',
                ],
                'en' => [
                    'variant_description' => '',
                ],
            ],
        ]);

    $response->assertRedirect(route('backend.collectible-copies.index'));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $user->id,
        'is_variant' => true,
    ]);

    $this->assertDatabaseHas('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
        'variant_description' => 'Bijzondere kleurafwijking',
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 1);
});

test('een gebruiker kan een eigen verzamelexemplaar verwijderen', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.collectible-copies.destroy', $collectibleCopy));

    $response->assertRedirect(route('backend.collectible-copies.index'));

    $response->assertSessionHas(
        'success',
        __('Verzamelexemplaar succesvol uit je verzameling verwijderd.')
    );

    $this->assertDatabaseMissing('collectible_copies', [
        'id' => $collectibleCopy->id,
    ]);

    $this->assertDatabaseHas('collectibles', [
        'id' => $collectible->id,
        'name' => 'Test FDC',
    ]);
});

test('een gebruiker kan het verzamelexemplaar van een andere gebruiker niet verwijderen', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $owner->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->delete(route('backend.collectible-copies.destroy', $collectibleCopy));

    $response->assertNotFound();

    $this->assertDatabaseHas('collectible_copies', [
        'id' => $collectibleCopy->id,
        'user_id' => $owner->id,
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);
});

test('variantvertalingen worden verwijderd wanneer een verzamelexemplaar wordt verwijderd', function () {
    $user = User::factory()->create();

    $country = Country::factory()->create();

    $collectibleType = CollectibleType::create([
        'slug' => 'fdc',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectible = Collectible::create([
        'collectible_type_id' => $collectibleType->id,
        'country_id' => $country->id,
        'name' => 'Test FDC',
        'issue_year' => 2026,
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $collectibleCopy = $user->collectibleCopies()->create([
        'collectible_id' => $collectible->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
        'is_variant' => true,
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'nl',
        'variant_description' => 'Bijzondere kleurafwijking',
    ]);

    $collectibleCopy->translations()->create([
        'locale' => 'en',
        'variant_description' => 'Special colour variation',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 2);

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.collectible-copies.destroy', $collectibleCopy));

    $response->assertRedirect(route('backend.collectible-copies.index'));

    $this->assertDatabaseMissing('collectible_copies', [
        'id' => $collectibleCopy->id,
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'nl',
    ]);

    $this->assertDatabaseMissing('collectible_copy_translations', [
        'collectible_copy_id' => $collectibleCopy->id,
        'locale' => 'en',
    ]);

    $this->assertDatabaseCount('collectible_copy_translations', 0);

    $this->assertDatabaseHas('collectibles', [
        'id' => $collectible->id,
        'name' => 'Test FDC',
    ]);
});
