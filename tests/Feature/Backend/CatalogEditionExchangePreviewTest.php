<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Imports\Exchange\CatalogEditionExchangePreview;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\SeriesCatalogValue;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampSeries;
use App\Models\Theme;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

test('de Excel-voorbeeldweergave leest catalogusnaam editienaam en editiejaar correct uit', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus voorbeeldweergave',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een echt Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        ExcelFormat::XLSX
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_preview_');

    try {
        file_put_contents($filePath, $excelContent);

        // Lees de gegevens via de nieuwe previewklasse.
        $preview = app(CatalogEditionExchangePreview::class)
            ->getPreview($filePath);

        // Controleer de catalogusnaam.
        expect($preview['catalog_name'])
            ->toBe('Testcatalogus voorbeeldweergave');

        // Controleer de editienaam.
        expect($preview['edition_name'])
            ->toBe('Testeditie 2026');

        // Controleer het editiejaar.
        expect($preview['edition_year'])
            ->toBe(2026);

        // Controleer het aantal landen en gebieden.
        expect($preview['country_count'])
            ->toBe(0);

        // Controleer het aantal postzegelseries.
        expect($preview['series_count'])
            ->toBe(0);

        // Controleer het aantal postzegels.
        expect($preview['stamp_count'])
            ->toBe(0);

        // Controleer het aantal thema's.
        expect($preview['theme_count'])
            ->toBe(0);

        // Controleer dat de preview uitsluitend
        // de zeven verwachte gegevensvelden bevat.
        expect(array_keys($preview))
            ->toBe([
                'catalog_name',
                'edition_name',
                'edition_year',
                'country_count',
                'series_count',
                'stamp_count',
                'theme_count',
            ]);

        // Controleer dat de voorbeeldweergave
        // geen extra cataloguseditie heeft aangemaakt.
        expect(
            CatalogEdition::where('catalog_id', $catalog->id)->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});

test('de Excel-voorbeeldweergave telt meerdere landen correct', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met landen',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie met drie landen',
        'edition_year' => 2026,
    ]);

    // Maak drie landen aan.
    $netherlands = Country::create([
        'name' => 'Nederland',
        'iso_code' => 'NL',
        'entity_type' => 'country',
        'slug' => 'nederland-preview-test',
        'is_active' => true,
    ]);

    $belgium = Country::create([
        'name' => 'België',
        'iso_code' => 'BE',
        'entity_type' => 'country',
        'slug' => 'belgie-preview-test',
        'is_active' => true,
    ]);

    $germany = Country::create([
        'name' => 'Duitsland',
        'iso_code' => 'DE',
        'entity_type' => 'country',
        'slug' => 'duitsland-preview-test',
        'is_active' => true,
    ]);

    // Koppel de drie landen aan de catalogus.
    $catalog->countries()->attach([
        $netherlands->id,
        $belgium->id,
        $germany->id,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        ExcelFormat::XLSX
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_preview_');

    try {
        file_put_contents($filePath, $excelContent);

        // Lees de Excel-voorbeeldweergave.
        $preview = app(CatalogEditionExchangePreview::class)
            ->getPreview($filePath);

        // Controleer dat precies drie landen zijn gevonden.
        expect($preview['country_count'])->toBe(3);

        // Controleer dat de preview geen extra landen aanmaakt.
        expect(
            Country::whereIn('id', [
                $netherlands->id,
                $belgium->id,
                $germany->id,
            ])->count()
        )->toBe(3);

        // Controleer dat geen extra cataloguseditie is aangemaakt.
        expect(
            CatalogEdition::where('catalog_id', $catalog->id)->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});

test('de Excel-voorbeeldweergave telt meerdere postzegelseries correct', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met postzegelseries',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie met drie postzegelseries',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'name' => 'Nederland',
        'iso_code' => 'NL',
        'entity_type' => 'country',
        'slug' => 'nederland-series-preview-test',
        'is_active' => true,
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'postfris-series-preview-test',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak drie postzegelseries aan.
    $series = [];

    foreach (['Bloemen', 'Vogels', 'Molens'] as $name) {
        $series[] = StampSeries::create([
            'country_id' => $country->id,
            'name' => $name,
            'issue_year' => 2026,
        ]);
    }

    // Koppel iedere serie via een cataloguswaarde aan de editie.
    foreach ($series as $index => $stampSeries) {
        SeriesCatalogValue::create([
            'catalog_edition_id' => $edition->id,
            'stamp_series_id' => $stampSeries->id,
            'condition_id' => $condition->id,
            'catalog_number' => 'SER-'.($index + 1),
            'catalog_value' => 10.00,
            'currency' => 'EUR',
        ]);
    }

    // Genereer een echt Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        ExcelFormat::XLSX
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_preview_');

    try {
        file_put_contents($filePath, $excelContent);

        // Lees de Excel-voorbeeldweergave.
        $preview = app(CatalogEditionExchangePreview::class)
            ->getPreview($filePath);

        // Controleer dat precies drie postzegelseries zijn gevonden.
        expect($preview['series_count'])->toBe(3);

        // Controleer dat de preview geen extra series aanmaakt.
        expect(
            StampSeries::whereIn(
                'id',
                array_map(fn ($item) => $item->id, $series)
            )->count()
        )->toBe(3);

        // Controleer dat geen extra cataloguseditie is aangemaakt.
        expect(
            CatalogEdition::where('catalog_id', $catalog->id)->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});

test('de Excel-voorbeeldweergave telt meerdere postzegels correct', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met postzegels',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie met drie postzegels',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'name' => 'Nederland',
        'iso_code' => 'NL',
        'entity_type' => 'country',
        'slug' => 'nederland-stamps-preview-test',
        'is_active' => true,
    ]);

    // Maak drie postzegels aan.
    $stamps = [];

    foreach (['Tulp', 'Molen', 'Vuurtoren'] as $name) {
        $stamps[] = Stamp::create([
            'country_id' => $country->id,
            'name' => $name,
            'issue_year' => 2026,
        ]);
    }

    // Koppel iedere postzegel aan de cataloguseditie.
    foreach ($stamps as $index => $stamp) {
        StampCatalogEntry::create([
            'catalog_edition_id' => $edition->id,
            'stamp_id' => $stamp->id,
            'catalog_number' => 'TEST-'.($index + 1),
        ]);
    }

    // Genereer een echt Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        ExcelFormat::XLSX
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_preview_');

    try {
        file_put_contents($filePath, $excelContent);

        // Lees de Excel-voorbeeldweergave.
        $preview = app(CatalogEditionExchangePreview::class)
            ->getPreview($filePath);

        // Controleer dat precies drie postzegels zijn gevonden.
        expect($preview['stamp_count'])->toBe(3);

        // Controleer dat de preview geen extra postzegels aanmaakt.
        expect(
            Stamp::whereIn(
                'id',
                array_map(fn ($item) => $item->id, $stamps)
            )->count()
        )->toBe(3);

        // Controleer dat geen extra cataloguseditie is aangemaakt.
        expect(
            CatalogEdition::where('catalog_id', $catalog->id)->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});

test('de Excel-voorbeeldweergave telt meerdere themas correct', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus met themas',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie met drie themas',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'name' => 'Nederland',
        'iso_code' => 'NL',
        'entity_type' => 'country',
        'slug' => 'nederland-themes-preview-test',
        'is_active' => true,
    ]);

    // Maak een postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel met themas',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => 'THEMA-001',
    ]);

    // Maak drie thema's aan.
    $themes = [];

    foreach ([
        'Bloemen' => 'bloemen-preview-test',
        'Vogels' => 'vogels-preview-test',
        'Natuur' => 'natuur-preview-test',
    ] as $name => $slug) {
        $themes[] = Theme::create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    // Koppel alle drie de thema's aan dezelfde postzegel.
    $stamp->themes()->attach(
        array_map(fn ($theme) => $theme->id, $themes)
    );

    // Genereer een echt Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        ExcelFormat::XLSX
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_preview_');

    try {
        file_put_contents($filePath, $excelContent);

        // Lees de Excel-voorbeeldweergave.
        $preview = app(CatalogEditionExchangePreview::class)
            ->getPreview($filePath);

        // Controleer dat precies drie thema's zijn gevonden.
        expect($preview['theme_count'])->toBe(3);

        // Controleer dat de preview geen extra thema's aanmaakt.
        expect(
            Theme::whereIn(
                'id',
                array_map(fn ($theme) => $theme->id, $themes)
            )->count()
        )->toBe(3);

        // Controleer dat geen extra cataloguseditie is aangemaakt.
        expect(
            CatalogEdition::where('catalog_id', $catalog->id)->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});
