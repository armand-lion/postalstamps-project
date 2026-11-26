<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\SeriesCatalogValue;
use App\Models\StampSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een postzegelserie met cataloguswaarden kan niet worden verwijderd', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $country = Country::create([
        'slug' => 'testland-serieverwijdering',
        'iso_code' => 'TSV',
        'name' => 'Testland serieverwijdering',
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus serieverwijdering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie verwijderbeveiliging',
        'issue_year' => 2026,
    ]);

    $value = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => null,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Te behouden seriecataloguswaarde',
    ]);

    // Controleer dat uitsluitend cataloguswaarden gekoppeld zijn.
    expect($series->stamps()->exists())->toBeFalse()
        ->and($series->catalogValues()->exists())->toBeTrue();

    $originalValue = $value->fresh()->getAttributes();

    // Probeer de postzegelserie te verwijderen.
    $response = $this
        ->actingAs($admin)
        ->delete(
            route('backend.stamp-series.destroy', $series)
        );

    // Controleer dat de verwijdering wordt geweigerd.
    $response->assertRedirect(
        route('backend.stamp-series.index')
    );

    $response->assertSessionHas(
        'error',
        "Postzegelserie '{$series->name}' kan niet worden verwijderd, omdat er nog postzegels of cataloguswaarden aan gekoppeld zijn."
    );

    // Controleer dat beide records behouden blijven.
    $this->assertDatabaseHas('stamp_series', [
        'id' => $series->id,
    ]);

    $this->assertDatabaseHas('series_catalog_values', [
        'id' => $value->id,
    ]);

    // Controleer dat de cataloguswaarde volledig ongewijzigd blijft.
    expect($value->fresh()->getAttributes())->toEqual($originalValue);

    // Er mogen geen geschiedenisregistraties ontstaan.
    $this->assertDatabaseCount('catalog_change_batches', 0);
    $this->assertDatabaseCount('catalog_change_logs', 0);
});
