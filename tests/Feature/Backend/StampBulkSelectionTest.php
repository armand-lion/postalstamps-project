<?php

use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use App\Services\StampBulkSelectionService;

test('een beheerder heeft toegang tot de bulkbeheerpagina', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('backend.stamps.bulk-edit.index'))
        ->assertOk();
});

test('een gewone gebruiker heeft geen toegang tot bulkbeheer', function () {
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->actingAs($user)
        ->get(route('backend.stamps.bulk-edit.index'))
        ->assertForbidden();

    $this->post(route('backend.stamps.bulk-edit.selection.update'), [
        'action' => 'add',
        'stamp_ids' => [1],
    ])->assertForbidden();

    $this->post(
        route('backend.stamps.bulk-edit.selection.all-filtered')
    )->assertForbidden();

    $this->delete(
        route('backend.stamps.bulk-edit.selection.clear')
    )->assertForbidden();

    $this->get(
        route('backend.stamps.bulk-edit.selected')
    )->assertForbidden();
});

test('een nieuwe selectie is standaard leeg', function () {
    $user = User::factory()->create();

    $service = app(StampBulkSelectionService::class);

    expect($service->getSelectedIds($user->id))->toBe([])
        ->and($service->count($user->id))->toBe(0);
});

test('het wissen van een selectie heeft geen invloed op andere gebruikers', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstKey = "stamp_bulk_selection.{$firstUser->id}";
    $secondKey = "stamp_bulk_selection.{$secondUser->id}";

    session()->put($firstKey, [1, 2]);
    session()->put($secondKey, [3, 4]);

    $service = app(StampBulkSelectionService::class);

    $service->clear($firstUser->id);

    expect(session()->has($firstKey))->toBeFalse()
        ->and(session()->get($secondKey))->toBe([3, 4]);
});

test('ongeldige postzegel ids worden geweigerd', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('backend.stamps.bulk-edit.selection.update'), [
            'action' => 'add',
            'stamp_ids' => [-1],
        ])
        ->assertSessionHasErrors('stamp_ids.0');
});

test('postzegels kunnen worden geselecteerd en gedeselecteerd zonder dubbele ids', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamps = Stamp::factory()->count(3)->create();

    $service = app(StampBulkSelectionService::class);

    $this->actingAs($admin);

    $this->post(route('backend.stamps.bulk-edit.selection.update'), [
        'action' => 'add',
        'stamp_ids' => [$stamps[0]->id, $stamps[1]->id],
    ])->assertRedirect();

    $this->post(route('backend.stamps.bulk-edit.selection.update'), [
        'action' => 'add',
        'stamp_ids' => [$stamps[1]->id, $stamps[2]->id],
    ])->assertRedirect();

    expect($service->count($admin->id))->toBe(3);

    $this->post(route('backend.stamps.bulk-edit.selection.update'), [
        'action' => 'remove',
        'stamp_ids' => [$stamps[1]->id],
    ])->assertRedirect();

    expect($service->count($admin->id))->toBe(2)
        ->and($service->isSelected($admin->id, $stamps[0]->id))->toBeTrue()
        ->and($service->isSelected($admin->id, $stamps[1]->id))->toBeFalse()
        ->and($service->isSelected($admin->id, $stamps[2]->id))->toBeTrue();

    $this->delete(
        route('backend.stamps.bulk-edit.selection.clear')
    )->assertRedirect();

    expect($service->count($admin->id))->toBe(0);
});

test('alle gefilterde postzegels worden geselecteerd onafhankelijk van paginering', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $country = Country::factory()->create();

    $filteredStamps = Stamp::factory()->count(25)->create([
        'country_id' => $country->id,
    ]);

    $outsideStamp = Stamp::factory()->create();

    $service = app(StampBulkSelectionService::class);

    $this->actingAs($admin);

    $service->add($admin->id, [$outsideStamp->id]);

    $this->post(route('backend.stamps.bulk-edit.selection.all-filtered', [
        'country_id' => $country->id,
    ]))->assertRedirect();

    expect($service->count($admin->id))->toBe(26)
        ->and($service->isSelected($admin->id, $outsideStamp->id))->toBeTrue();

    foreach ($filteredStamps as $stamp) {
        expect($service->isSelected($admin->id, $stamp->id))->toBeTrue();
    }

    $this->get(route('backend.stamps.bulk-edit.index', [
        'country_id' => $country->id,
    ]))
        ->assertOk()
        ->assertViewHas('selectedCount', 26)
        ->assertViewHas('selectedWithinFiltersCount', 25)
        ->assertViewHas('selectedOutsideFiltersCount', 1);

    $newStamp = Stamp::factory()->create([
        'country_id' => $country->id,
    ]);

    expect($service->isSelected($admin->id, $newStamp->id))->toBeFalse()
        ->and($service->count($admin->id))->toBe(26);
});

test('selecties van verschillende gebruikers blijven gescheiden', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $firstStamp = Stamp::factory()->create();
    $secondStamp = Stamp::factory()->create();

    $service = app(StampBulkSelectionService::class);

    $service->add($firstUser->id, [$firstStamp->id]);
    $service->add($secondUser->id, [$secondStamp->id]);

    expect($service->getSelectedIds($firstUser->id))->toBe([$firstStamp->id])
        ->and($service->getSelectedIds($secondUser->id))->toBe([$secondStamp->id]);
});

test('verwijderde postzegels worden automatisch uit de selectie verwijderd', function () {
    $user = User::factory()->create();

    $firstStamp = Stamp::factory()->create();
    $secondStamp = Stamp::factory()->create();

    $service = app(StampBulkSelectionService::class);

    $service->add($user->id, [
        $firstStamp->id,
        $secondStamp->id,
    ]);

    $firstStamp->delete();

    expect($service->getSelectedIds($user->id))->toBe([$secondStamp->id])
        ->and($service->count($user->id))->toBe(1)
        ->and(session()->get("stamp_bulk_selection.{$user->id}"))
        ->toBe([$secondStamp->id]);
});
