<?php

use App\Models\CatalogEdition;
use App\Services\CatalogEditionComparisonService;

test('de service berekent een waardestijging correct', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk EUR 10,00 met EUR 15,00.
    $result = $service->calculateValueChange(
        10.00,
        15.00,
        'EUR',
        'EUR'
    );

    // Controleer het absolute verschil.
    expect($result['absolute'])->toBe(5.00);

    // Controleer het procentuele verschil.
    expect($result['percentage'])->toBe(50.00);
});

test('de service berekent een waardedaling correct', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk EUR 20,00 met EUR 15,00.
    $result = $service->calculateValueChange(
        20.00,
        15.00,
        'EUR',
        'EUR'
    );

    // Controleer het absolute verschil.
    expect($result['absolute'])->toBe(-5.00);

    // Controleer het procentuele verschil.
    expect($result['percentage'])->toBe(-25.00);
});

test('de service herkent ongewijzigde cataloguswaarden', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk twee gelijke cataloguswaarden.
    $result = $service->calculateValueChange(
        10.00,
        10.00,
        'EUR',
        'EUR'
    );

    // Er is geen absoluut verschil.
    expect($result['absolute'])->toBe(0.00);

    // Er is geen procentueel verschil.
    expect($result['percentage'])->toBe(0.00);
});

test('de service berekent geen verschil bij ontbrekende cataloguswaarden', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // De vorige cataloguswaarde ontbreekt.
    $result = $service->calculateValueChange(
        null,
        15.00,
        'EUR',
        'EUR'
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();

    // De nieuwe cataloguswaarde ontbreekt.
    $result = $service->calculateValueChange(
        10.00,
        null,
        'EUR',
        'EUR'
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();

    // Beide cataloguswaarden ontbreken.
    $result = $service->calculateValueChange(
        null,
        null,
        'EUR',
        'EUR'
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();
});

test('de service berekent geen percentage bij een beginwaarde van nul', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk EUR 0,00 met EUR 10,00.
    $result = $service->calculateValueChange(
        0.00,
        10.00,
        'EUR',
        'EUR'
    );

    // Het absolute verschil kan worden berekend.
    expect($result['absolute'])->toBe(10.00);

    // Een procentuele verandering kan niet worden berekend.
    expect($result['percentage'])->toBeNull();
});

test('de service berekent een waardedaling van honderd procent bij een eindwaarde van nul', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk EUR 10,00 met EUR 0,00.
    $result = $service->calculateValueChange(
        10.00,
        0.00,
        'EUR',
        'EUR'
    );

    // Controleer de absolute waardedaling.
    expect($result['absolute'])->toBe(-10.00);

    // Controleer de procentuele waardedaling.
    expect($result['percentage'])->toBe(-100.00);
});

test('de service berekent geen verschil bij verschillende valuta', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Vergelijk EUR 10,00 met USD 15,00.
    $result = $service->calculateValueChange(
        10.00,
        15.00,
        'EUR',
        'USD'
    );

    // Verschillende valuta mogen niet rechtstreeks worden vergeleken.
    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();
});

test('de service berekent geen verschil bij ontbrekende valuta', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // De valuta van de vorige cataloguswaarde ontbreekt.
    $result = $service->calculateValueChange(
        10.00,
        15.00,
        null,
        'EUR'
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();

    // De valuta van de nieuwe cataloguswaarde ontbreekt.
    $result = $service->calculateValueChange(
        10.00,
        15.00,
        'EUR',
        null
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();

    // Beide valuta ontbreken.
    $result = $service->calculateValueChange(
        10.00,
        15.00,
        null,
        null
    );

    expect($result['absolute'])->toBeNull();
    expect($result['percentage'])->toBeNull();
});

test('de service weigert edities van verschillende catalogi te vergelijken', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Maak een editie van de eerste catalogus aan.
    $previousEdition = new CatalogEdition;
    $previousEdition->id = 1;
    $previousEdition->catalog_id = 1;

    // Maak een editie van een andere catalogus aan.
    $currentEdition = new CatalogEdition;
    $currentEdition->id = 2;
    $currentEdition->catalog_id = 2;

    // De service moet deze vergelijking weigeren.
    expect(fn () => $service->compareEditions(
        $previousEdition,
        $currentEdition
    ))->toThrow(
        InvalidArgumentException::class,
        'De catalogusedities moeten tot dezelfde catalogus behoren.'
    );
});

test('de service weigert een cataloguseditie met zichzelf te vergelijken', function () {
    // Maak de vergelijkingsservice aan.
    $service = new CatalogEditionComparisonService;

    // Maak een cataloguseditie aan.
    $edition = new CatalogEdition;
    $edition->id = 1;
    $edition->catalog_id = 1;

    // De service moet een vergelijking met dezelfde editie weigeren.
    expect(fn () => $service->compareEditions(
        $edition,
        $edition
    ))->toThrow(
        InvalidArgumentException::class,
        'Selecteer twee verschillende catalogusedities.'
    );
});
