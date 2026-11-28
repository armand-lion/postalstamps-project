<?php

use App\Models\Stamp;
use App\Models\StampChangeBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'Beheerder geschiedeniscontrole',
        'is_admin' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'is_admin' => false,
    ]);

    $this->stamp = Stamp::factory()->create([
        'name' => 'Testpostzegel geschiedenis',
    ]);

    $this->batch = StampChangeBatch::create([
        'user_id' => $this->admin->id,
        'user_name' => $this->admin->name,
        'operation' => 'bulk_edit',
        'description' => 'Bulkbewerking algemene postzegelgegevens',
        'stamp_count' => 1,
        'operations' => [
            'designer_operation' => 'set',
            'designer' => 'Nieuwe ontwerper',
        ],
    ]);

    $this->batch->logs()->create([
        'stamp_id' => $this->stamp->id,
        'stamp_name' => $this->stamp->name,
        'old_values' => [
            'designer' => 'Oude ontwerper',
        ],
        'new_values' => [
            'designer' => 'Nieuwe ontwerper',
        ],
    ]);
});

test('beheerders kunnen de postzegelgeschiedenis inclusief beheerdersgegevens bekijken', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('backend.stamps.history.index'));

    $response
        ->assertOk()
        ->assertSee('Uitgevoerd door')
        ->assertSee($this->admin->name);

    $batch = $response->viewData('batches')->first();

    expect($batch->getAttributes())
        ->toHaveKey('user_id')
        ->toHaveKey('user_name');

    $response = $this->get(
        route('backend.stamps.history.show', $this->batch->id)
    );

    $response
        ->assertOk()
        ->assertSee('Uitgevoerd door')
        ->assertSee($this->admin->name)
        ->assertSee('Oude ontwerper')
        ->assertSee('Nieuwe ontwerper');

    expect($response->viewData('batch')->getAttributes())
        ->toHaveKey('user_id')
        ->toHaveKey('user_name');
});

test('gewone gebruikers kunnen de postzegelgeschiedenis zonder beheerdersgegevens bekijken', function () {
    $response = $this->actingAs($this->regularUser)
        ->get(route('backend.stamps.history.index'));

    $response
        ->assertOk()
        ->assertDontSee('Uitgevoerd door')
        ->assertDontSee($this->admin->name)
        ->assertSee('Bulkbewerking');

    $batch = $response->viewData('batches')->first();

    expect($batch->getAttributes())
        ->not->toHaveKey('user_id')
        ->not->toHaveKey('user_name');

    $response = $this->get(
        route('backend.stamps.history.show', $this->batch->id)
    );

    $response
        ->assertOk()
        ->assertDontSee('Uitgevoerd door')
        ->assertDontSee($this->admin->name)
        ->assertSee('Oude ontwerper')
        ->assertSee('Nieuwe ontwerper');

    expect($response->viewData('batch')->getAttributes())
        ->not->toHaveKey('user_id')
        ->not->toHaveKey('user_name');
});

test('niet ingelogde bezoekers hebben geen toegang tot de postzegelgeschiedenis', function () {
    $this->get(route('backend.stamps.history.index'))
        ->assertRedirect(route('login'));

    $this->get(
        route('backend.stamps.history.show', $this->batch->id)
    )->assertRedirect(route('login'));
});
