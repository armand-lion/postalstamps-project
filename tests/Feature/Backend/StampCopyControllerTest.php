<?php

use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCopy;
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
        'stamp_id' => $stampPostfris->id,
        'condition_id' => $postfris->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
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
        'stamp_id' => $stampNederlandPostfris->id,
        'condition_id' => $postfris->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
        'stamp_id' => $stampNederlandGestempeld->id,
        'condition_id' => $gestempeld->id,
        'quantity' => 1,
    ]);

    StampCopy::create([
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
