<?php

use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCopy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('quantity can be assigned and is cast to an integer', function () {
    $stampCopy = new StampCopy;

    $stampCopy->fill([
        'quantity' => '3',
    ]);

    expect($stampCopy->quantity)->toBe(3);
});

test('a stamp copy has a default quantity of one', function () {
    $stamp = Stamp::factory()->create();

    $user = User::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
    ]);

    $stampCopy->refresh();

    expect($stampCopy->quantity)->toBe(1);
});

test('a stamp copy can store multiple identical stamps', function () {
    $stamp = Stamp::factory()->create();

    $user = User::factory()->create();

    $stampCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 5,
    ]);

    $stampCopy->refresh();

    expect($stampCopy->quantity)->toBe(5);

    $this->assertDatabaseHas('stamp_copies', [
        'id' => $stampCopy->id,
        'stamp_id' => $stamp->id,
        'quantity' => 5,
    ]);
});

test('a stamp can have copies with different conditions', function () {
    $stamp = Stamp::factory()->create();

    $mint = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $used = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    $mintCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $mint->id,
        'quantity' => 3,
    ]);

    $usedCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $used->id,
        'quantity' => 2,
    ]);

    expect($mintCopy->fresh()->quantity)->toBe(3)
        ->and($usedCopy->fresh()->quantity)->toBe(2)
        ->and($mintCopy->condition->slug)->toBe('postfris')
        ->and($usedCopy->condition->slug)->toBe('gestempeld');

    $this->assertDatabaseCount('stamp_copies', 2);
});

test('a stamp can have a separate variant copy', function () {
    $stamp = Stamp::factory()->create();

    $user = User::factory()->create();

    $normalCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 3,
        'is_variant' => false,
    ]);

    $variantCopy = StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
        'is_variant' => true,
        'variant_description' => 'Afwijkende tanding',
    ]);

    expect($normalCopy->fresh()->quantity)->toBe(3)
        ->and($normalCopy->is_variant)->toBeFalse()
        ->and($variantCopy->fresh()->quantity)->toBe(1)
        ->and($variantCopy->is_variant)->toBeTrue()
        ->and($variantCopy->variant_description)->toBe('Afwijkende tanding');

    $this->assertDatabaseCount('stamp_copies', 2);
});

test('a stamp can retrieve its owned copies', function () {
    $stamp = Stamp::factory()->create();

    $user = User::factory()->create();

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 3,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 2,
    ]);

    $stamp->refresh();

    expect($stamp->copies)->toHaveCount(2)
        ->and($stamp->copies->sum('quantity'))->toBe(5);
});

test('a stamp without copies is not owned', function () {
    $stamp = Stamp::factory()->create();

    expect($stamp->isOwned())->toBeFalse();
});

test('a stamp with copies is owned', function () {
    $stamp = Stamp::factory()->create();

    $user = User::factory()->create();

    $this->actingAs($user);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    expect($stamp->isOwned())->toBeTrue();
});

test('a stamp owned by another user is not owned', function () {
    $stamp = Stamp::factory()->create();

    // Gebruiker 1 bezit de postzegel.
    $owner = User::factory()->create();

    StampCopy::create([
        'user_id' => $owner->id,
        'stamp_id' => $stamp->id,
        'quantity' => 1,
    ]);

    // Gebruiker 2 bezit deze postzegel niet.
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser);

    expect($stamp->isOwned())->toBeFalse();
});
