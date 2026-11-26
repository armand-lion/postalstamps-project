<?php

use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('het verwijderen van een gebruiker behoudt de catalogusgeschiedenis en oorspronkelijke gebruikersnaam', function () {
    $user = User::factory()->create([
        'name' => 'Oorspronkelijke beheerder',
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus gebruikersverwijdering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-gebruikersverwijdering',
        'iso_code' => 'TGV',
        'name' => 'Testland gebruikersverwijdering',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel gebruikersverwijdering',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    $history = app(CatalogChangeHistoryService::class);

    // Maak een geschiedenisbatch aan voor de gebruiker.
    $batch = $history->createBatch(
        $edition,
        'import_replace',
        $user,
        'Testgeschiedenis gebruikersverwijdering.'
    );

    $history->setActiveBatch($batch);

    try {
        // Registreer een daadwerkelijke cataloguswijziging.
        $value->update([
            'catalog_value' => '50.00',
        ]);
    } finally {
        $history->clearActiveBatch();
    }

    $log = $batch->logs()->sole();

    // Controleer de oorspronkelijke geschiedenisregistratie.
    expect($batch->user_id)->toBe($user->id)
        ->and($batch->user_name)->toBe('Oorspronkelijke beheerder')
        ->and($log->record_type)->toBe('stamp_catalog_value')
        ->and($log->action)->toBe('updated')
        ->and($log->record_id)->toBe($value->id)
        ->and((float) $log->old_values['catalog_value'])->toBe(25.50)
        ->and((float) $log->new_values['catalog_value'])->toBe(50.00);

    // Bewaar de volledige oorspronkelijke gegevens.
    $originalBatch = $batch->fresh()->getAttributes();
    $originalLog = $log->fresh()->getAttributes();
    $originalValue = $value->fresh()->getAttributes();

    $userId = $user->id;

    // Verwijder de gebruiker.
    $user->delete();

    $this->assertDatabaseMissing('users', [
        'id' => $userId,
    ]);

    // Controleer dat de geschiedenisbatch behouden blijft.
    $batch->refresh();

    expect($batch->user_id)->toBeNull()
        ->and($batch->user_name)->toBe('Oorspronkelijke beheerder')
        ->and($batch->user)->toBeNull()
        ->and($batch->catalog_edition_id)->toBe($edition->id)
        ->and($batch->operation)->toBe('import_replace')
        ->and($batch->logs()->count())->toBe(1);

    // Alleen de gebruikersreferentie mag zijn gewijzigd.
    $originalBatch['user_id'] = null;

    expect($batch->getAttributes())->toEqual($originalBatch);

    // Controleer dat de wijzigingslog en snapshots intact zijn.
    expect($log->fresh()->getAttributes())->toEqual($originalLog);

    // Controleer dat de cataloguswaarde intact is.
    expect($value->fresh()->getAttributes())->toEqual($originalValue);

    // Controleer dat geen geschiedenis verloren is gegaan.
    expect(CatalogChangeBatch::query()->count())->toBe(1);

    $this->assertDatabaseCount('catalog_change_logs', 1);

    expect($history->getActiveBatch())->toBeNull();
});
