<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Services\CatalogEditionComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('de service retourneert een leeg resultaat bij twee lege catalogusedities', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege edities',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide lege catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer of het resultaat een lege array is.
    expect($results)->toBe([]);
});

test('de service herkent dezelfde postzegel in beide catalogusedities', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande postzegel',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel aan de vorige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-001',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-001',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegelgegevens.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['stamp']->id)->toBe($stamp->id);

    // Controleer de catalogusnummers.
    expect($result['previous_catalog_number'])->toBe('TEST-001');
    expect($result['current_catalog_number'])->toBe('TEST-001');

    // Controleer de vergelijkingsstatus.
    expect($result['status'])->toBe('existing');

    // Er zijn nog geen cataloguswaarden geregistreerd.
    expect($result['conditions'])->toBe([]);
});

test('de service herkent een nieuw opgenomen postzegel in de huidige cataloguseditie', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus nieuw opgenomen postzegel',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel uitsluitend aan de huidige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-002',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegelgegevens.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['stamp']->id)->toBe($stamp->id);

    // Controleer de catalogusnummers.
    expect($result['previous_catalog_number'])->toBeNull();
    expect($result['current_catalog_number'])->toBe('TEST-002');

    // Controleer de vergelijkingsstatus.
    expect($result['status'])->toBe('added');

    // Er zijn nog geen cataloguswaarden geregistreerd.
    expect($result['conditions'])->toBe([]);
});

test('de service herkent een niet meer opgenomen postzegel in de huidige cataloguseditie', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus niet meer opgenomen postzegel',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel uitsluitend aan de vorige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-003',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegelgegevens.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['stamp']->id)->toBe($stamp->id);

    // Controleer de catalogusnummers.
    expect($result['previous_catalog_number'])->toBe('TEST-003');
    expect($result['current_catalog_number'])->toBeNull();

    // Controleer de vergelijkingsstatus.
    expect($result['status'])->toBe('removed');

    // Er zijn nog geen cataloguswaarden geregistreerd.
    expect($result['conditions'])->toBe([]);
});

test('de service herkent gewijzigde catalogusnummers van dezelfde postzegel', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus gewijzigde catalogusnummers',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel met het oorspronkelijke catalogusnummer.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-004',
    ]);

    // Koppel dezelfde postzegel met een gewijzigd catalogusnummer.
    StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-104',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegelgegevens.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['stamp']->id)->toBe($stamp->id);

    // Controleer beide verschillende catalogusnummers.
    expect($result['previous_catalog_number'])->toBe('TEST-004');
    expect($result['current_catalog_number'])->toBe('TEST-104');

    // Controleer of de postzegel als bestaand wordt herkend.
    expect($result['status'])->toBe('existing');

    // Er zijn nog geen cataloguswaarden geregistreerd.
    expect($result['conditions'])->toBe([]);
});

test('de service verwerkt een waardestijging tussen twee catalogusedities correct', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus waardestijging',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-005',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-005',
    ]);

    // Registreer de oorspronkelijke cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de verhoogde cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Controleer de conditiegegevens.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($conditionResult['condition']->id)->toBe($condition->id);

    // Controleer beide cataloguswaarden.
    expect($conditionResult['previous_value'])->toBe(10.00);
    expect($conditionResult['current_value'])->toBe(15.00);

    // Controleer beide valuta.
    expect($conditionResult['previous_currency'])->toBe('EUR');
    expect($conditionResult['current_currency'])->toBe('EUR');

    // Controleer de absolute en procentuele waardestijging.
    expect($conditionResult['absolute_change'])->toBe(5.00);
    expect($conditionResult['percentage_change'])->toBe(50.00);

    // Controleer de waardeveranderingsstatus.
    expect($conditionResult['status'])->toBe('increased');
});

test('de service verwerkt een waardedaling tussen twee catalogusedities correct', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus waardedaling',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-006',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-006',
    ]);

    // Registreer de oorspronkelijke cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
    ]);

    // Registreer de verlaagde cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Controleer de conditiegegevens.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($conditionResult['condition']->id)->toBe($condition->id);

    // Controleer beide cataloguswaarden.
    expect($conditionResult['previous_value'])->toBe(20.00);
    expect($conditionResult['current_value'])->toBe(15.00);

    // Controleer beide valuta.
    expect($conditionResult['previous_currency'])->toBe('EUR');
    expect($conditionResult['current_currency'])->toBe('EUR');

    // Controleer de absolute en procentuele waardedaling.
    expect($conditionResult['absolute_change'])->toBe(-5.00);
    expect($conditionResult['percentage_change'])->toBe(-25.00);

    // Controleer de waardeveranderingsstatus.
    expect($conditionResult['status'])->toBe('decreased');
});

test('de service herkent ongewijzigde cataloguswaarden tussen twee catalogusedities', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongewijzigde cataloguswaarden',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-007',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-007',
    ]);

    // Registreer de oorspronkelijke cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Registreer dezelfde cataloguswaarde in de huidige editie.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Controleer de conditiegegevens.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($conditionResult['condition']->id)->toBe($condition->id);

    // Controleer beide cataloguswaarden.
    expect($conditionResult['previous_value'])->toBe(15.00);
    expect($conditionResult['current_value'])->toBe(15.00);

    // Controleer beide valuta.
    expect($conditionResult['previous_currency'])->toBe('EUR');
    expect($conditionResult['current_currency'])->toBe('EUR');

    // Controleer dat beide waardeveranderingen nul zijn.
    expect($conditionResult['absolute_change'])->toBe(0.00);
    expect($conditionResult['percentage_change'])->toBe(0.00);

    // Controleer de waardeveranderingsstatus.
    expect($conditionResult['status'])->toBe('unchanged');
});

test('de service vergelijkt meerdere waarderingscondities onafhankelijk van elkaar', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere waarderingscondities',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Ongebruikt met plakker aan.
    $ongebruikt = Condition::create([
        'slug' => 'ongebruikt-met-plakker',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Gestempeld aan.
    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-008',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-008',
    ]);

    // Registreer de vorige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
    ]);

    // Registreer de vorige cataloguswaarde voor Ongebruikt met plakker.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de vorige cataloguswaarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 30.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde voor Ongebruikt met plakker.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => 8.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(3);

    // Indexeer de waarderingsresultaten per conditie-ID.
    $conditionResults = collect($result['conditions'])
        ->keyBy('condition_id');

    // Haal het waarderingsresultaat voor Postfris op.
    $postfrisResult = $conditionResults->get($postfris->id);

    // Controleer de waardestijging voor Postfris.
    expect($postfrisResult['condition_id'])->toBe($postfris->id);
    expect($postfrisResult['previous_value'])->toBe(20.00);
    expect($postfrisResult['current_value'])->toBe(30.00);
    expect($postfrisResult['absolute_change'])->toBe(10.00);
    expect($postfrisResult['percentage_change'])->toBe(50.00);
    expect($postfrisResult['status'])->toBe('increased');

    // Haal het waarderingsresultaat voor Ongebruikt met plakker op.
    $ongebruiktResult = $conditionResults->get($ongebruikt->id);

    // Controleer de waardedaling voor Ongebruikt met plakker.
    expect($ongebruiktResult['condition_id'])->toBe($ongebruikt->id);
    expect($ongebruiktResult['previous_value'])->toBe(10.00);
    expect($ongebruiktResult['current_value'])->toBe(8.00);
    expect($ongebruiktResult['absolute_change'])->toBe(-2.00);
    expect($ongebruiktResult['percentage_change'])->toBe(-20.00);
    expect($ongebruiktResult['status'])->toBe('decreased');

    // Haal het waarderingsresultaat voor Gestempeld op.
    $gestempeldResult = $conditionResults->get($gestempeld->id);

    // Controleer de ongewijzigde cataloguswaarde voor Gestempeld.
    expect($gestempeldResult['condition_id'])->toBe($gestempeld->id);
    expect($gestempeldResult['previous_value'])->toBe(5.00);
    expect($gestempeldResult['current_value'])->toBe(5.00);
    expect($gestempeldResult['absolute_change'])->toBe(0.00);
    expect($gestempeldResult['percentage_change'])->toBe(0.00);
    expect($gestempeldResult['status'])->toBe('unchanged');
});

test('de service herkent een toegevoegde cataloguswaarde in de huidige cataloguseditie', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus toegevoegde cataloguswaarde',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Gestempeld aan.
    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-009',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-009',
    ]);

    // Registreer de vorige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Registreer uitsluitend in de huidige editie een waarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Beide waarderingscondities moeten aanwezig zijn.
    expect($result['conditions'])->toHaveCount(2);

    // Indexeer de waarderingsresultaten per conditie-ID.
    $conditionResults = collect($result['conditions'])
        ->keyBy('condition_id');

    // Haal het waarderingsresultaat voor Postfris op.
    $postfrisResult = $conditionResults->get($postfris->id);

    // Controleer de bestaande cataloguswaarde.
    expect($postfrisResult['condition_id'])->toBe($postfris->id);
    expect($postfrisResult['previous_value'])->toBe(10.00);
    expect($postfrisResult['current_value'])->toBe(15.00);
    expect($postfrisResult['absolute_change'])->toBe(5.00);
    expect($postfrisResult['percentage_change'])->toBe(50.00);
    expect($postfrisResult['status'])->toBe('increased');

    // Haal het waarderingsresultaat voor Gestempeld op.
    $gestempeldResult = $conditionResults->get($gestempeld->id);

    // Controleer de toegevoegde cataloguswaarde.
    expect($gestempeldResult['condition_id'])->toBe($gestempeld->id);
    expect($gestempeldResult['condition']->id)->toBe($gestempeld->id);
    expect($gestempeldResult['previous_value'])->toBeNull();
    expect($gestempeldResult['current_value'])->toBe(5.00);
    expect($gestempeldResult['previous_currency'])->toBeNull();
    expect($gestempeldResult['current_currency'])->toBe('EUR');

    // Zonder vorige cataloguswaarde mag geen verschil worden berekend.
    expect($gestempeldResult['absolute_change'])->toBeNull();
    expect($gestempeldResult['percentage_change'])->toBeNull();

    // Controleer de waardeveranderingsstatus.
    expect($gestempeldResult['status'])->toBe('added');
});

test('de service herkent een verwijderde cataloguswaarde in de huidige cataloguseditie', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus verwijderde cataloguswaarde',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Gestempeld aan.
    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-010',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-010',
    ]);

    // Registreer de vorige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Registreer uitsluitend in de vorige editie een waarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Beide waarderingscondities moeten aanwezig zijn.
    expect($result['conditions'])->toHaveCount(2);

    // Indexeer de waarderingsresultaten per conditie-ID.
    $conditionResults = collect($result['conditions'])
        ->keyBy('condition_id');

    // Haal het waarderingsresultaat voor Postfris op.
    $postfrisResult = $conditionResults->get($postfris->id);

    // Controleer de bestaande cataloguswaarde.
    expect($postfrisResult['condition_id'])->toBe($postfris->id);
    expect($postfrisResult['previous_value'])->toBe(10.00);
    expect($postfrisResult['current_value'])->toBe(15.00);
    expect($postfrisResult['absolute_change'])->toBe(5.00);
    expect($postfrisResult['percentage_change'])->toBe(50.00);
    expect($postfrisResult['status'])->toBe('increased');

    // Haal het waarderingsresultaat voor Gestempeld op.
    $gestempeldResult = $conditionResults->get($gestempeld->id);

    // Controleer de verwijderde cataloguswaarde.
    expect($gestempeldResult['condition_id'])->toBe($gestempeld->id);
    expect($gestempeldResult['condition']->id)->toBe($gestempeld->id);
    expect($gestempeldResult['previous_value'])->toBe(5.00);
    expect($gestempeldResult['current_value'])->toBeNull();
    expect($gestempeldResult['previous_currency'])->toBe('EUR');
    expect($gestempeldResult['current_currency'])->toBeNull();

    // Zonder huidige cataloguswaarde mag geen verschil worden berekend.
    expect($gestempeldResult['absolute_change'])->toBeNull();
    expect($gestempeldResult['percentage_change'])->toBeNull();

    // Controleer de waardeveranderingsstatus.
    expect($gestempeldResult['status'])->toBe('removed');
});

test('de service vergelijkt geen cataloguswaarden met verschillende valuta', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus verschillende valuta',
    ]);

    // Maak de vorige cataloguseditie met EUR aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie met USD aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-011',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-011',
    ]);

    // Registreer de vorige cataloguswaarde in EUR.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde in USD.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'USD',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Controleer beide catalogusnummers.
    expect($result['previous_catalog_number'])->toBe('TEST-011');
    expect($result['current_catalog_number'])->toBe('TEST-011');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Controleer de conditiegegevens.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($conditionResult['condition']->id)->toBe($condition->id);

    // Beide oorspronkelijke cataloguswaarden moeten behouden blijven.
    expect($conditionResult['previous_value'])->toBe(10.00);
    expect($conditionResult['current_value'])->toBe(15.00);

    // Beide oorspronkelijke valuta moeten behouden blijven.
    expect($conditionResult['previous_currency'])->toBe('EUR');
    expect($conditionResult['current_currency'])->toBe('USD');

    // Bij verschillende valuta mag geen verschil worden berekend.
    expect($conditionResult['absolute_change'])->toBeNull();
    expect($conditionResult['percentage_change'])->toBeNull();

    // Controleer de waardeveranderingsstatus.
    expect($conditionResult['status'])->toBe('not_comparable');
});

test('de service verwerkt ontbrekende cataloguswaarden binnen bestaande waarderingsregistraties correct', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende cataloguswaarden',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Ongebruikt met plakker aan.
    $ongebruikt = Condition::create([
        'slug' => 'ongebruikt-met-plakker',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Gestempeld aan.
    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-012',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-012',
    ]);

    // Registreer een ontbrekende vorige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => null,
        'currency' => 'EUR',
    ]);

    // Registreer een bekende huidige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Registreer een bekende vorige cataloguswaarde voor Ongebruikt.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer een ontbrekende huidige cataloguswaarde voor Ongebruikt.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => null,
        'currency' => 'EUR',
    ]);

    // Registreer een ontbrekende vorige cataloguswaarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => null,
        'currency' => 'EUR',
    ]);

    // Registreer een ontbrekende huidige cataloguswaarde voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => null,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Alle drie de waarderingscondities moeten aanwezig zijn.
    expect($result['conditions'])->toHaveCount(3);

    // Indexeer de waarderingsresultaten per conditie-ID.
    $conditionResults = collect($result['conditions'])
        ->keyBy('condition_id');

    // Haal het waarderingsresultaat voor Postfris op.
    $postfrisResult = $conditionResults->get($postfris->id);

    // Controleer de ontbrekende vorige cataloguswaarde.
    expect($postfrisResult['condition_id'])->toBe($postfris->id);
    expect($postfrisResult['condition']->id)->toBe($postfris->id);
    expect($postfrisResult['previous_value'])->toBeNull();
    expect($postfrisResult['current_value'])->toBe(15.00);
    expect($postfrisResult['previous_currency'])->toBe('EUR');
    expect($postfrisResult['current_currency'])->toBe('EUR');
    expect($postfrisResult['absolute_change'])->toBeNull();
    expect($postfrisResult['percentage_change'])->toBeNull();
    expect($postfrisResult['status'])->toBe('added');

    // Haal het waarderingsresultaat voor Ongebruikt met plakker op.
    $ongebruiktResult = $conditionResults->get($ongebruikt->id);

    // Controleer de ontbrekende huidige cataloguswaarde.
    expect($ongebruiktResult['condition_id'])->toBe($ongebruikt->id);
    expect($ongebruiktResult['condition']->id)->toBe($ongebruikt->id);
    expect($ongebruiktResult['previous_value'])->toBe(10.00);
    expect($ongebruiktResult['current_value'])->toBeNull();
    expect($ongebruiktResult['previous_currency'])->toBe('EUR');
    expect($ongebruiktResult['current_currency'])->toBe('EUR');
    expect($ongebruiktResult['absolute_change'])->toBeNull();
    expect($ongebruiktResult['percentage_change'])->toBeNull();
    expect($ongebruiktResult['status'])->toBe('removed');

    // Haal het waarderingsresultaat voor Gestempeld op.
    $gestempeldResult = $conditionResults->get($gestempeld->id);

    // Controleer twee ontbrekende cataloguswaarden.
    expect($gestempeldResult['condition_id'])->toBe($gestempeld->id);
    expect($gestempeldResult['condition']->id)->toBe($gestempeld->id);
    expect($gestempeldResult['previous_value'])->toBeNull();
    expect($gestempeldResult['current_value'])->toBeNull();
    expect($gestempeldResult['previous_currency'])->toBe('EUR');
    expect($gestempeldResult['current_currency'])->toBe('EUR');
    expect($gestempeldResult['absolute_change'])->toBeNull();
    expect($gestempeldResult['percentage_change'])->toBeNull();
    expect($gestempeldResult['status'])->toBe('not_comparable');
});

test('de service verwerkt expliciet geregistreerde nulwaarden correct', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus nulwaarden',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Ongebruikt met plakker aan.
    $ongebruikt = Condition::create([
        'slug' => 'ongebruikt-met-plakker',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak de waarderingsconditie Gestempeld aan.
    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-013',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-013',
    ]);

    // Registreer een vorige cataloguswaarde van nul voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    // Registreer een hogere huidige cataloguswaarde voor Postfris.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer een positieve vorige cataloguswaarde voor Ongebruikt.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer een huidige cataloguswaarde van nul voor Ongebruikt.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $ongebruikt->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    // Registreer een vorige cataloguswaarde van nul voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    // Registreer dezelfde nulwaarde in de huidige editie voor Gestempeld.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Alle drie de waarderingscondities moeten aanwezig zijn.
    expect($result['conditions'])->toHaveCount(3);

    // Indexeer de waarderingsresultaten per conditie-ID.
    $conditionResults = collect($result['conditions'])
        ->keyBy('condition_id');

    // Haal het waarderingsresultaat voor Postfris op.
    $postfrisResult = $conditionResults->get($postfris->id);

    // Controleer de waardestijging vanaf nul.
    expect($postfrisResult['condition_id'])->toBe($postfris->id);
    expect($postfrisResult['condition']->id)->toBe($postfris->id);
    expect($postfrisResult['previous_value'])->toBe(0.00);
    expect($postfrisResult['current_value'])->toBe(10.00);
    expect($postfrisResult['previous_currency'])->toBe('EUR');
    expect($postfrisResult['current_currency'])->toBe('EUR');
    expect($postfrisResult['absolute_change'])->toBe(10.00);
    expect($postfrisResult['percentage_change'])->toBeNull();
    expect($postfrisResult['status'])->toBe('increased');

    // Haal het waarderingsresultaat voor Ongebruikt met plakker op.
    $ongebruiktResult = $conditionResults->get($ongebruikt->id);

    // Controleer de waardedaling naar nul.
    expect($ongebruiktResult['condition_id'])->toBe($ongebruikt->id);
    expect($ongebruiktResult['condition']->id)->toBe($ongebruikt->id);
    expect($ongebruiktResult['previous_value'])->toBe(10.00);
    expect($ongebruiktResult['current_value'])->toBe(0.00);
    expect($ongebruiktResult['previous_currency'])->toBe('EUR');
    expect($ongebruiktResult['current_currency'])->toBe('EUR');
    expect($ongebruiktResult['absolute_change'])->toBe(-10.00);
    expect($ongebruiktResult['percentage_change'])->toBe(-100.00);
    expect($ongebruiktResult['status'])->toBe('decreased');

    // Haal het waarderingsresultaat voor Gestempeld op.
    $gestempeldResult = $conditionResults->get($gestempeld->id);

    // Controleer twee ongewijzigde nulwaarden.
    expect($gestempeldResult['condition_id'])->toBe($gestempeld->id);
    expect($gestempeldResult['condition']->id)->toBe($gestempeld->id);
    expect($gestempeldResult['previous_value'])->toBe(0.00);
    expect($gestempeldResult['current_value'])->toBe(0.00);
    expect($gestempeldResult['previous_currency'])->toBe('EUR');
    expect($gestempeldResult['current_currency'])->toBe('EUR');
    expect($gestempeldResult['absolute_change'])->toBe(0.00);
    expect($gestempeldResult['percentage_change'])->toBeNull();
    expect($gestempeldResult['status'])->toBe('unchanged');
});

test('de service verwerkt meerdere postzegels met en zonder cataloguswaarden correct', function () {
    // Maak een testpostzegel met cataloguswaarden aan.
    $stampWithValues = Stamp::factory()->create();

    // Maak een bestaande testpostzegel zonder cataloguswaarden aan.
    $stampWithoutValues = Stamp::factory()->create();

    // Maak een nieuw opgenomen testpostzegel zonder cataloguswaarden aan.
    $addedStamp = Stamp::factory()->create();

    // Maak een niet meer opgenomen testpostzegel zonder cataloguswaarden aan.
    $removedStamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus postzegels zonder cataloguswaarden',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel met cataloguswaarden aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stampWithValues->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-014',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stampWithValues->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-014',
    ]);

    // Registreer de vorige cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel zonder cataloguswaarden aan de vorige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stampWithoutValues->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-015',
    ]);

    // Koppel dezelfde postzegel zonder cataloguswaarden aan de huidige editie.
    StampCatalogEntry::create([
        'stamp_id' => $stampWithoutValues->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-015',
    ]);

    // Koppel de nieuw opgenomen postzegel uitsluitend aan de huidige editie.
    StampCatalogEntry::create([
        'stamp_id' => $addedStamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-016',
    ]);

    // Koppel de niet meer opgenomen postzegel uitsluitend aan de vorige editie.
    StampCatalogEntry::create([
        'stamp_id' => $removedStamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-017',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Alle vier de postzegels moeten in het resultaat aanwezig zijn.
    expect($results)->toHaveCount(4);

    // Indexeer de vergelijkingsresultaten per postzegel-ID.
    $stampResults = collect($results)->keyBy('stamp_id');

    // Haal het resultaat van de postzegel met cataloguswaarden op.
    $stampWithValuesResult = $stampResults->get($stampWithValues->id);

    // Controleer de postzegel met cataloguswaarden.
    expect($stampWithValuesResult['stamp_id'])->toBe($stampWithValues->id);
    expect($stampWithValuesResult['stamp']->id)->toBe($stampWithValues->id);
    expect($stampWithValuesResult['previous_catalog_number'])->toBe('TEST-014');
    expect($stampWithValuesResult['current_catalog_number'])->toBe('TEST-014');
    expect($stampWithValuesResult['status'])->toBe('existing');
    expect($stampWithValuesResult['conditions'])->toHaveCount(1);

    // Haal de waarderingsvergelijking op.
    $conditionResult = $stampWithValuesResult['conditions'][0];

    // Controleer de waardestijging.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($conditionResult['previous_value'])->toBe(10.00);
    expect($conditionResult['current_value'])->toBe(15.00);
    expect($conditionResult['absolute_change'])->toBe(5.00);
    expect($conditionResult['percentage_change'])->toBe(50.00);
    expect($conditionResult['status'])->toBe('increased');

    // Haal het resultaat van de bestaande postzegel zonder waarden op.
    $stampWithoutValuesResult = $stampResults->get($stampWithoutValues->id);

    // Controleer de bestaande postzegel zonder cataloguswaarden.
    expect($stampWithoutValuesResult['stamp_id'])->toBe($stampWithoutValues->id);
    expect($stampWithoutValuesResult['stamp']->id)->toBe($stampWithoutValues->id);
    expect($stampWithoutValuesResult['previous_catalog_number'])->toBe('TEST-015');
    expect($stampWithoutValuesResult['current_catalog_number'])->toBe('TEST-015');
    expect($stampWithoutValuesResult['status'])->toBe('existing');
    expect($stampWithoutValuesResult['conditions'])->toBe([]);

    // Haal het resultaat van de nieuw opgenomen postzegel op.
    $addedStampResult = $stampResults->get($addedStamp->id);

    // Controleer de nieuw opgenomen postzegel zonder cataloguswaarden.
    expect($addedStampResult['stamp_id'])->toBe($addedStamp->id);
    expect($addedStampResult['stamp']->id)->toBe($addedStamp->id);
    expect($addedStampResult['previous_catalog_number'])->toBeNull();
    expect($addedStampResult['current_catalog_number'])->toBe('TEST-016');
    expect($addedStampResult['status'])->toBe('added');
    expect($addedStampResult['conditions'])->toBe([]);

    // Haal het resultaat van de niet meer opgenomen postzegel op.
    $removedStampResult = $stampResults->get($removedStamp->id);

    // Controleer de niet meer opgenomen postzegel zonder cataloguswaarden.
    expect($removedStampResult['stamp_id'])->toBe($removedStamp->id);
    expect($removedStampResult['stamp']->id)->toBe($removedStamp->id);
    expect($removedStampResult['previous_catalog_number'])->toBe('TEST-017');
    expect($removedStampResult['current_catalog_number'])->toBeNull();
    expect($removedStampResult['status'])->toBe('removed');
    expect($removedStampResult['conditions'])->toBe([]);
});

test('de service laadt alle benodigde modelrelaties en vertalingen vooraf', function () {
    // Maak een testland aan.
    $country = Country::factory()->create([
        'name' => 'Nederland',
        'iso_code' => 'NL',
        'slug' => 'nederland',
    ]);

    // Registreer de Nederlandse landvertaling.
    $country->translations()->create([
        'locale' => 'nl',
        'name' => 'Nederland',
        'description' => 'Europees land',
    ]);

    // Registreer de Engelse landvertaling.
    $country->translations()->create([
        'locale' => 'en',
        'name' => 'Netherlands',
        'description' => 'European country',
    ]);

    // Maak een testpostzegel voor het testland aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $country->id,
        'name' => 'Bloemen en planten',
        'issue_year' => 2020,
    ]);

    // Registreer de Nederlandse postzegelvertaling.
    $stamp->translations()->create([
        'locale' => 'nl',
        'name' => 'Bloemen en planten',
        'description' => 'Postzegel met bloemen en planten',
    ]);

    // Registreer de Engelse postzegelvertaling.
    $stamp->translations()->create([
        'locale' => 'en',
        'name' => 'Flowers and plants',
        'description' => 'Stamp featuring flowers and plants',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus modelrelaties en vertalingen',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Registreer de Nederlandse conditievertaling.
    $condition->translations()->create([
        'locale' => 'nl',
        'name' => 'Postfris',
        'description' => 'Ongebruikt met originele gom zonder plakker',
    ]);

    // Registreer de Engelse conditievertaling.
    $condition->translations()->create([
        'locale' => 'en',
        'name' => 'Mint never hinged',
        'description' => 'Unused with original gum and no hinge',
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-018',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-018',
    ]);

    // Registreer de vorige cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Registreer de huidige cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Controleer het aantal vergelijkingsresultaten.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de vergelijkingsstatus.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['status'])->toBe('existing');

    // Haal het teruggegeven Stamp-model op.
    $resultStamp = $result['stamp'];

    // Controleer de identiteit en het modeltype.
    expect($resultStamp)->toBeInstanceOf(Stamp::class);
    expect($resultStamp->id)->toBe($stamp->id);

    // Controleer of beide Stamp-relaties vooraf zijn geladen.
    expect($resultStamp->relationLoaded('translations'))->toBeTrue();
    expect($resultStamp->relationLoaded('country'))->toBeTrue();

    // Controleer het aantal postzegelvertalingen.
    expect($resultStamp->translations)->toHaveCount(2);

    // Haal de Nederlandse en Engelse postzegelvertalingen op.
    $stampNl = $resultStamp->translations->firstWhere('locale', 'nl');
    $stampEn = $resultStamp->translations->firstWhere('locale', 'en');

    // Controleer de postzegelvertalingen.
    expect($stampNl->name)->toBe('Bloemen en planten');
    expect($stampNl->description)->toBe('Postzegel met bloemen en planten');
    expect($stampEn->name)->toBe('Flowers and plants');
    expect($stampEn->description)->toBe('Stamp featuring flowers and plants');

    // Haal het vooraf geladen Country-model op.
    $resultCountry = $resultStamp->country;

    // Controleer de identiteit en het modeltype.
    expect($resultCountry)->toBeInstanceOf(Country::class);
    expect($resultCountry->id)->toBe($country->id);

    // Controleer of de landvertalingen vooraf zijn geladen.
    expect($resultCountry->relationLoaded('translations'))->toBeTrue();

    // Controleer het aantal landvertalingen.
    expect($resultCountry->translations)->toHaveCount(2);

    // Haal de Nederlandse en Engelse landvertalingen op.
    $countryNl = $resultCountry->translations->firstWhere('locale', 'nl');
    $countryEn = $resultCountry->translations->firstWhere('locale', 'en');

    // Controleer de landvertalingen.
    expect($countryNl->name)->toBe('Nederland');
    expect($countryNl->description)->toBe('Europees land');
    expect($countryEn->name)->toBe('Netherlands');
    expect($countryEn->description)->toBe('European country');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Haal het teruggegeven Condition-model op.
    $resultCondition = $conditionResult['condition'];

    // Controleer de identiteit en het modeltype.
    expect($conditionResult['condition_id'])->toBe($condition->id);
    expect($resultCondition)->toBeInstanceOf(Condition::class);
    expect($resultCondition->id)->toBe($condition->id);

    // Controleer of de conditievertalingen vooraf zijn geladen.
    expect($resultCondition->relationLoaded('translations'))->toBeTrue();

    // Controleer het aantal conditievertalingen.
    expect($resultCondition->translations)->toHaveCount(2);

    // Haal de Nederlandse en Engelse conditievertalingen op.
    $conditionNl = $resultCondition->translations->firstWhere('locale', 'nl');
    $conditionEn = $resultCondition->translations->firstWhere('locale', 'en');

    // Controleer de conditievertalingen.
    expect($conditionNl->name)->toBe('Postfris');
    expect($conditionNl->description)->toBe('Ongebruikt met originele gom zonder plakker');
    expect($conditionEn->name)->toBe('Mint never hinged');
    expect($conditionEn->description)->toBe('Unused with original gum and no hinge');

    // Controleer of de waardevergelijking correct blijft functioneren.
    expect($conditionResult['previous_value'])->toBe(10.00);
    expect($conditionResult['current_value'])->toBe(15.00);
    expect($conditionResult['previous_currency'])->toBe('EUR');
    expect($conditionResult['current_currency'])->toBe('EUR');
    expect($conditionResult['absolute_change'])->toBe(5.00);
    expect($conditionResult['percentage_change'])->toBe(50.00);
    expect($conditionResult['status'])->toBe('increased');
});

test('de service wijzigt geen databasegegevens tijdens het vergelijken van catalogusedities', function () {
    // Maak een testpostzegel aan.
    $stamp = Stamp::factory()->create();

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus alleen-lezen vergelijking',
    ]);

    // Maak de vorige cataloguseditie aan.
    $previousEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke vorige cataloguseditie',
    ]);

    // Maak de huidige cataloguseditie aan.
    $currentEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke huidige cataloguseditie',
    ]);

    // Maak de waarderingsconditie Postfris aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan de vorige editie.
    $previousEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $previousEdition->id,
        'catalog_number' => 'TEST-019',
        'notes' => 'Oorspronkelijke vorige catalogusregistratie',
    ]);

    // Koppel dezelfde postzegel aan de huidige editie.
    $currentEntry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $currentEdition->id,
        'catalog_number' => 'TEST-119',
        'notes' => 'Oorspronkelijke huidige catalogusregistratie',
    ]);

    // Registreer de vorige cataloguswaarde zonder controlemarkering.
    $previousValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $previousEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke vorige cataloguswaarde',
        'needs_review' => false,
    ]);

    // Registreer de huidige cataloguswaarde met controlemarkering.
    $currentValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $currentEntry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke huidige cataloguswaarde',
        'needs_review' => true,
    ]);

    // Bepaal welke databasetabellen ongewijzigd moeten blijven.
    $tables = [
        'catalogs',
        'catalog_editions',
        'stamps',
        'stamp_catalog_entries',
        'stamp_catalog_values',
    ];

    // Bereid de oorspronkelijke databasesnapshot voor.
    $databaseBefore = [];

    // Leg de volledige inhoud van iedere tabel vast.
    foreach ($tables as $table) {
        $databaseBefore[$table] = DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    // Verplaats de testtijd om wijzigingen in updated_at
    // eveneens betrouwbaar te kunnen detecteren.
    $this->travel(1)->day();

    // Maak een instantie van de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk beide catalogusedities.
    $results = $service->compareEditions(
        $previousEdition,
        $currentEdition
    );

    // Bereid de databasesnapshot na de vergelijking voor.
    $databaseAfter = [];

    // Leg opnieuw de volledige inhoud van iedere tabel vast.
    foreach ($tables as $table) {
        $databaseAfter[$table] = DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    // Herstel de oorspronkelijke testtijd.
    $this->travelBack();

    // Controleer of de vergelijking een resultaat heeft opgeleverd.
    expect($results)->toHaveCount(1);

    // Haal het vergelijkingsresultaat op.
    $result = $results[0];

    // Controleer de postzegel en de gewijzigde catalogusnummers.
    expect($result['stamp_id'])->toBe($stamp->id);
    expect($result['previous_catalog_number'])->toBe('TEST-019');
    expect($result['current_catalog_number'])->toBe('TEST-119');
    expect($result['status'])->toBe('existing');

    // Controleer het aantal waarderingscondities.
    expect($result['conditions'])->toHaveCount(1);

    // Haal het waarderingsresultaat op.
    $conditionResult = $result['conditions'][0];

    // Controleer of de waardevergelijking correct is uitgevoerd.
    expect($conditionResult['previous_value'])->toBe(10.00);
    expect($conditionResult['current_value'])->toBe(15.00);
    expect($conditionResult['absolute_change'])->toBe(5.00);
    expect($conditionResult['percentage_change'])->toBe(50.00);
    expect($conditionResult['status'])->toBe('increased');

    // Controleer of iedere databasetabel volledig ongewijzigd is.
    foreach ($tables as $table) {
        expect($databaseAfter[$table])->toBe($databaseBefore[$table]);
    }

    // Laad beide oorspronkelijke cataloguswaarden opnieuw.
    $previousValue->refresh();
    $currentValue->refresh();

    // Controleer of beide controlemarkeringen behouden zijn.
    expect($previousValue->needs_review)->toBeFalse();
    expect($currentValue->needs_review)->toBeTrue();

    // Controleer of beide oorspronkelijke opmerkingen behouden zijn.
    expect($previousValue->notes)->toBe('Oorspronkelijke vorige cataloguswaarde');
    expect($currentValue->notes)->toBe('Oorspronkelijke huidige cataloguswaarde');
});
