<?php

use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Services\CatalogEditionDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een cataloguseditie kan niet naar zichzelf worden gekopieerd', function () {
    $edition = new CatalogEdition([
        'catalog_id' => 1,
    ]);

    $edition->id = 1;

    $service = app(CatalogEditionDuplicationService::class);

    $service->validateEditions($edition, $edition);
})->throws(
    InvalidArgumentException::class,
    'Een cataloguseditie kan niet naar zichzelf worden gekopieerd.'
);

test('catalogusedities moeten bij dezelfde catalogus horen', function () {
    $sourceEdition = new CatalogEdition([
        'catalog_id' => 1,
    ]);

    $sourceEdition->id = 1;

    $targetEdition = new CatalogEdition([
        'catalog_id' => 2,
    ]);

    $targetEdition->id = 2;

    $service = app(CatalogEditionDuplicationService::class);

    $service->validateEditions($sourceEdition, $targetEdition);
})->throws(
    InvalidArgumentException::class,
    'De bron- en doeleditie moeten bij dezelfde catalogus horen.'
);

test('een postzegelvermelding wordt naar een andere cataloguseditie gekopieerd', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus duplicatie',
        'abbreviation' => 'TCD',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
        'notes' => 'Testopmerking',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition
    );

    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    $copiedEntry = $targetEdition->stampEntries()->first();

    expect($copiedEntry)->not->toBeNull()
        ->stamp_id->toBe($stamp->id)
        ->catalog_number->toBe('123a')
        ->notes->toBe('Testopmerking');
});

test('bestaande postzegelvermeldingen worden niet overschreven', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande vermeldingen',
        'abbreviation' => 'TCB',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
        'notes' => 'Opmerking broneditie',
    ]);

    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $targetEdition->id,
        'catalog_number' => '456b',
        'notes' => 'Opmerking doeleditie',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition
    );

    expect($result)->toBe([
        'copied' => 0,
        'skipped' => 1,
    ]);

    $targetEntry = $targetEdition->stampEntries()->firstOrFail();

    expect($targetEdition->stampEntries()->count())->toBe(1)
        ->and($targetEntry->catalog_number)->toBe('456b')
        ->and($targetEntry->notes)->toBe('Opmerking doeleditie');
});

test('cataloguswaarden worden inclusief nulwaarden gekopieerd', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus waarderingen dupliceren',
        'abbreviation' => 'TCWD',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

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

    $sourceEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
    ]);

    $sourceEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 15.75,
        'currency' => 'EUR',
        'notes' => 'Waarde postfris',
    ]);

    $sourceEntry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
        'notes' => 'Waarde gestempeld',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition,
        true
    );

    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    $targetEntry = $targetEdition->stampEntries()->firstOrFail();

    $values = $targetEntry->values()
        ->get()
        ->keyBy('condition_id');

    expect($values)->toHaveCount(2)
        ->and($values[$postfris->id]->catalog_value)->toBe('15.75')
        ->and($values[$postfris->id]->currency)->toBe('EUR')
        ->and($values[$postfris->id]->notes)->toBe('Waarde postfris')
        ->and($values[$gestempeld->id]->catalog_value)->toBe('0.00')
        ->and($values[$gestempeld->id]->currency)->toBe('EUR')
        ->and($values[$gestempeld->id]->notes)->toBe('Waarde gestempeld');
});

test('cataloguswaarden worden niet gekopieerd wanneer deze optie is uitgeschakeld', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus zonder waarderingen',
        'abbreviation' => 'TCZW',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $sourceEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
    ]);

    $sourceEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 15.75,
        'currency' => 'EUR',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition,
        false
    );

    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    $targetEntry = $targetEdition->stampEntries()->firstOrFail();

    expect($targetEntry->catalog_number)->toBe('123a')
        ->and($targetEntry->values()->count())->toBe(0)
        ->and($sourceEntry->values()->count())->toBe(1);
});

test('bestaande cataloguswaarden worden niet overschreven', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande waarderingen',
        'abbreviation' => 'TCBW',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $sourceEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
    ]);

    $sourceEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 15.75,
        'currency' => 'EUR',
    ]);

    $targetEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $targetEdition->id,
        'catalog_number' => '456b',
    ]);

    $targetEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 2.50,
        'currency' => 'EUR',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition,
        true
    );

    expect($result)->toBe([
        'copied' => 0,
        'skipped' => 1,
    ]);

    $targetEntry->refresh();

    expect($targetEntry->catalog_number)->toBe('456b')
        ->and($targetEntry->values()->count())->toBe(1)
        ->and($targetEntry->values()->firstOrFail()->catalog_value)->toBe('2.50');
});

test('alleen toegestane waarderingscategorieën worden gekopieerd', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus waarderingsregels',
        'abbreviation' => 'TCWR',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $sourceEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $targetEdition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

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

    // Alleen postfris is toegestaan binnen deze catalogus.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    $sourceEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $sourceEdition->id,
        'catalog_number' => '123a',
    ]);

    // De broneditie bevat beide waarderingscategorieën.
    $sourceEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 15.75,
        'currency' => 'EUR',
    ]);

    $sourceEntry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 7.50,
        'currency' => 'EUR',
    ]);

    $service = app(CatalogEditionDuplicationService::class);

    $result = $service->copyStampEntries(
        $sourceEdition,
        $targetEdition,
        true
    );

    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    $targetEntry = $targetEdition->stampEntries()->firstOrFail();

    expect($targetEntry->values()->count())->toBe(1)
        ->and($targetEntry->values()
            ->where('condition_id', $postfris->id)
            ->exists())->toBeTrue()
        ->and($targetEntry->values()
            ->where('condition_id', $gestempeld->id)
            ->exists())->toBeFalse()
        ->and($targetEntry->values()
            ->firstOrFail()
            ->catalog_value)->toBe('15.75');

    // De oorspronkelijke cataloguswaarden blijven behouden.
    expect($sourceEntry->values()->count())->toBe(2);
});
