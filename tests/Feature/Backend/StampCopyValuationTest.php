<?php

use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCopy;
use App\Models\StampCopyValuation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een gebruiker kan een persoonlijke waardering toevoegen', function () {
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
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.valuations.store', $stampCopy), [
            'valuation_date' => '2026-11-07',
            'value' => 25.50,
            'currency' => 'EUR',
            'source' => 'Eigen taxatie',
            'notes' => 'Postzegel in uitstekende staat.',
        ]);

    $response->assertRedirect(
        route('backend.stamp-copies.show', $stampCopy)
    );

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07 00:00:00',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Postzegel in uitstekende staat.',
    ]);
});

test('een gebruiker kan een persoonlijke waardering wijzigen', function () {
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
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-01',
        'value' => 20.00,
        'currency' => 'EUR',
        'source' => 'Eerste taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamp-copies.valuations.update', [
            $stampCopy,
            $valuation,
        ]), [
            'valuation_date' => '2026-11-07',
            'value' => 35.50,
            'currency' => 'EUR',
            'source' => 'Nieuwe taxatie',
            'notes' => 'Waardering aangepast.',
        ]);

    $response->assertRedirect(
        route('backend.stamp-copies.show', $stampCopy)
    );

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'id' => $valuation->id,
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07 00:00:00',
        'value' => 35.50,
        'currency' => 'EUR',
        'source' => 'Nieuwe taxatie',
        'notes' => 'Waardering aangepast.',
    ]);
});

test('een gebruiker kan een persoonlijke waardering verwijderen', function () {
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
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Persoonlijke waardering.',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamp-copies.valuations.destroy', [
            $stampCopy,
            $valuation,
        ]));

    $response->assertRedirect(
        route('backend.stamp-copies.edit', $stampCopy)
    );

    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('stamp_copy_valuations', [
        'id' => $valuation->id,
    ]);

    $this->assertDatabaseHas('stamp_copies', [
        'id' => $stampCopy->id,
    ]);

    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
    ]);
});

test('een gebruiker kan geen negatieve persoonlijke waardering toevoegen', function () {
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
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.valuations.store', $stampCopy), [
            'valuation_date' => '2026-11-07',
            'value' => -25.50,
            'currency' => 'EUR',
            'source' => 'Eigen taxatie',
            'notes' => 'Ongeldige negatieve waardering.',
        ]);

    $response->assertSessionHasErrors(['value']);

    $this->assertDatabaseMissing('stamp_copy_valuations', [
        'stamp_copy_id' => $stampCopy->id,
        'value' => -25.50,
    ]);
});

test('een gebruiker kan geen persoonlijke waardering toevoegen met een te lange bron', function () {
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
        'quantity' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('backend.stamp-copies.valuations.store', $stampCopy), [
            'valuation_date' => '2026-11-07',
            'value' => 25.50,
            'currency' => 'EUR',
            'source' => str_repeat('A', 151),
            'notes' => 'Test met een te lange bron.',
        ]);

    $response->assertSessionHasErrors(['source']);

    $this->assertDatabaseMissing('stamp_copy_valuations', [
        'stamp_copy_id' => $stampCopy->id,
        'source' => str_repeat('A', 151),
    ]);
});

test('een gebruiker kan een waardering niet via een ander verzamelexemplaar wijzigen', function () {
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
        'quantity' => 1,
    ]);

    $otherStampCopy = StampCopy::create([
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamp-copies.valuations.update', [
            $otherStampCopy,
            $valuation,
        ]), [
            'valuation_date' => '2026-11-07',
            'value' => 99.99,
            'currency' => 'EUR',
            'source' => 'Onjuiste wijziging',
            'notes' => 'Deze wijziging mag niet worden opgeslagen.',
        ]);

    $response->assertNotFound();

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'id' => $valuation->id,
        'stamp_copy_id' => $stampCopy->id,
        'value' => 25.50,
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);
});

test('een gebruiker kan een waardering niet via een ander verzamelexemplaar verwijderen', function () {
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
        'quantity' => 1,
    ]);

    $otherStampCopy = StampCopy::create([
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('backend.stamp-copies.valuations.destroy', [
            $otherStampCopy,
            $valuation,
        ]));

    $response->assertNotFound();

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'id' => $valuation->id,
        'stamp_copy_id' => $stampCopy->id,
        'value' => 25.50,
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);
});

test('een niet aangemelde gebruiker kan geen persoonlijke waardering toevoegen', function () {
    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampCopy = StampCopy::create([
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $response = $this->post(
        route('backend.stamp-copies.valuations.store', $stampCopy),
        [
            'valuation_date' => '2026-11-07',
            'value' => 25.50,
            'currency' => 'EUR',
            'source' => 'Eigen taxatie',
            'notes' => 'Waardering zonder aanmelding.',
        ]
    );

    $response->assertRedirect(route('login'));

    $this->assertDatabaseMissing('stamp_copy_valuations', [
        'stamp_copy_id' => $stampCopy->id,
        'source' => 'Eigen taxatie',
    ]);
});

test('een niet aangemelde gebruiker kan geen persoonlijke waardering wijzigen', function () {
    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampCopy = StampCopy::create([
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);

    $response = $this->put(
        route('backend.stamp-copies.valuations.update', [
            $stampCopy,
            $valuation,
        ]),
        [
            'valuation_date' => '2026-11-07',
            'value' => 99.99,
            'currency' => 'EUR',
            'source' => 'Ongeautoriseerde wijziging',
            'notes' => 'Deze wijziging mag niet worden opgeslagen.',
        ]
    );

    $response->assertRedirect(route('login'));

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'id' => $valuation->id,
        'stamp_copy_id' => $stampCopy->id,
        'value' => 25.50,
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);
});

test('een niet aangemelde gebruiker kan geen persoonlijke waardering verwijderen', function () {
    $stamp = Stamp::factory()->create();

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampCopy = StampCopy::create([
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 1,
    ]);

    $valuation = StampCopyValuation::create([
        'stamp_copy_id' => $stampCopy->id,
        'valuation_date' => '2026-11-07',
        'value' => 25.50,
        'currency' => 'EUR',
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);

    $response = $this->delete(
        route('backend.stamp-copies.valuations.destroy', [
            $stampCopy,
            $valuation,
        ])
    );

    $response->assertRedirect(route('login'));

    $this->assertDatabaseHas('stamp_copy_valuations', [
        'id' => $valuation->id,
        'stamp_copy_id' => $stampCopy->id,
        'value' => 25.50,
        'source' => 'Eigen taxatie',
        'notes' => 'Oorspronkelijke waardering.',
    ]);
});
