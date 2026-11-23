<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een beheerder kan de Excel-importpagina van een catalogus openen', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus Excel importinterface',
    ]);

    // Open de importpagina als beheerder.
    $response = $this
        ->actingAs($admin)
        ->get(route('backend.catalogs.editions.import.create', $catalog));

    // Controleer of de pagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de juiste catalogus wordt getoond.
    $response->assertSee('Testcatalogus Excel importinterface');

    // Controleer of het importformulier aanwezig is.
    $response->assertSee('Excel-bestand selecteren');
});

test('een gewone gebruiker kan de Excel-importpagina niet openen', function () {
    // Maak een gewone gebruiker zonder beheerdersrechten aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus toegangscontrole',
    ]);

    // Probeer de importpagina te openen als gewone gebruiker.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.catalogs.editions.import.create', $catalog));

    // Controleer of de toegang wordt geweigerd.
    $response->assertForbidden();
});

test('een gewone gebruiker kan geen Excel-bestand uploaden', function () {
    // Maak een gewone gebruiker zonder beheerdersrechten aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus beveiligde Excel-upload',
    ]);

    // Probeer de Excel-import via de POST-route te starten.
    $response = $this
        ->actingAs($user)
        ->post(route('backend.catalogs.editions.import.store', $catalog));

    // Controleer of de toegang wordt geweigerd.
    $response->assertForbidden();
});

test('een beheerder kan geen Excel-import starten zonder bestand', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend Excel-bestand',
    ]);

    // Verstuur het importformulier zonder Excel-bestand.
    $response = $this
        ->actingAs($admin)
        ->post(route('backend.catalogs.editions.import.store', $catalog));

    // Controleer of het verplichte bestand wordt afgewezen.
    $response->assertSessionHasErrors(['file']);

    // Controleer of er geen cataloguseditie is aangemaakt.
    $this->assertDatabaseCount('catalog_editions', 0);
});

test('een beheerder kan geen tekstbestand importeren', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldig bestandstype',
    ]);

    // Maak een tijdelijk tekstbestand aan.
    $file = UploadedFile::fake()->create(
        'catalogus.txt',
        10,
        'text/plain'
    );

    // Probeer het tekstbestand te importeren.
    $response = $this
        ->actingAs($admin)
        ->post(
            route('backend.catalogs.editions.import.store', $catalog),
            ['file' => $file]
        );

    // Controleer of het bestandstype wordt afgewezen.
    $response->assertSessionHasErrors(['file']);

    // Controleer of er geen cataloguseditie is aangemaakt.
    $this->assertDatabaseCount('catalog_editions', 0);
});

test('een beheerder kan geen ongeldig Excel-bestand importeren', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldig Excel-bestand',
    ]);

    // Maak een bestand met een Excel-extensie, maar ongeldige inhoud.
    $file = UploadedFile::fake()->createWithContent(
        'catalogus.xlsx',
        'Dit is geen geldig Excel-bestand.'
    );

    // Probeer het ongeldige Excel-bestand te importeren.
    $response = $this
        ->actingAs($admin)
        ->post(
            route('backend.catalogs.editions.import.store', $catalog),
            ['file' => $file]
        );

    // Controleer of het ongeldige bestand wordt afgewezen.
    $response->assertSessionHasErrors(['file']);

    // Controleer of er geen cataloguseditie is aangemaakt.
    $this->assertDatabaseCount('catalog_editions', 0);
});

test('een beheerder kan geen Excel-bestand importeren met ontbrekende tabbladen', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende tabbladen',
    ]);

    // Maak een geldig Excel-bestand met slechts één standaardtabblad.
    $spreadsheet = new Spreadsheet;

    $filePath = tempnam(sys_get_temp_dir(), 'catalogus_').'.xlsx';

    try {
        // Sla het Excel-bestand op.
        IOFactory::createWriter(
            $spreadsheet,
            'Xlsx'
        )->save($filePath);

        // Maak van het Excel-bestand een uploadbestand.
        $file = new UploadedFile(
            $filePath,
            'catalogus.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Verstuur het Excel-bestand als beheerder.
        $response = $this
            ->actingAs($admin)
            ->post(
                route('backend.catalogs.editions.import.store', $catalog),
                ['file' => $file]
            );

        // Controleer de specifieke foutmelding van onze Excel-validator.
        $response->assertSessionHasErrors([
            'file' => 'De volgende verplichte tabbladen ontbreken: '
                .'Metadata, Catalogus, Editie, Landen, Landvertalingen, '
                .'Waarderingsregels, Postzegelseries, Serievertalingen, '
                .'Postzegels, BovenliggendePostzegels, Postzegelvertalingen, '
                .'Cataloguswaarden, Themas, Themavertalingen, '
                .'Postzegelthemas, Waarderingscategorieen, '
                .'Waarderingsvertalingen, Seriecataloguswaarden',
        ]);

        // Controleer of er geen cataloguseditie is aangemaakt.
        $this->assertDatabaseCount('catalog_editions', 0);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        $spreadsheet->disconnectWorksheets();

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
});

test('een geldig Excel-bestand wordt gevalideerd zonder gegevens te importeren', function () {
    // Gebruik een geïsoleerde opslagomgeving voor deze test.
    Storage::fake('local');
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geldige Excel-upload',
    ]);

    // Maak een bestaande cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport(
            $catalog,
            $edition
        ),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    try {
        // Schrijf de Excel-inhoud naar het tijdelijke bestand.
        file_put_contents($filePath, $excelContent);

        // Maak een uploadbestand aan.
        $file = new UploadedFile(
            $filePath,
            'catalogus.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Upload het Excel-bestand als beheerder.
        $response = $this
            ->actingAs($admin)
            ->post(
                route('backend.catalogs.editions.import.store', $catalog),
                ['file' => $file]
            );

        // Controleer of de gebruiker naar de voorbeeldpagina wordt doorgestuurd.
        $response->assertRedirect(
            route('backend.catalogs.editions.import.preview', $catalog)
        );

        // Controleer of er geen extra cataloguseditie is aangemaakt.
        $this->assertDatabaseCount('catalog_editions', 1);

        // Controleer of de tijdelijke importgegevens in de sessie staan.
        $response->assertSessionHas(
            'catalog_edition_import.user_id',
            $admin->id
        );

        $response->assertSessionHas(
            'catalog_edition_import.catalog_id',
            $catalog->id
        );

        // Controleer of het gevalideerde Excel-bestand privé is opgeslagen.
        $storedFiles = Storage::disk('local')
            ->files('catalog-edition-imports');

        expect($storedFiles)->toHaveCount(1);

        // Controleer of het opgeslagen bestand daadwerkelijk bestaat.
        Storage::disk('local')
            ->assertExists($storedFiles[0]);

        // Controleer of het opgeslagen bestandspad overeenkomt
        // met het daadwerkelijk opgeslagen Excel-bestand.
        $response->assertSessionHas(
            'catalog_edition_import.file_path',
            $storedFiles[0]
        );

        // Controleer of het gevalideerde Excel-bestand privé is opgeslagen.
        $storedFiles = Storage::disk('local')
            ->files('catalog-edition-imports');

        expect($storedFiles)->toHaveCount(1);

        // Controleer of het opgeslagen bestand daadwerkelijk bestaat.
        Storage::disk('local')
            ->assertExists($storedFiles[0]);

    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
});

test('een beheerder kan de Excel-voorbeeldweergave niet openen zonder importgegevens', function () {
    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus voorbeeldweergave zonder sessie',
    ]);

    // Probeer de voorbeeldweergave te openen zonder importgegevens.
    $response = $this
        ->actingAs($admin)
        ->get(
            route('backend.catalogs.editions.import.preview', $catalog)
        );

    // Controleer of de beheerder teruggaat naar de importpagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.create', $catalog)
    );

    // Controleer of de juiste foutmelding wordt weergegeven.
    $response->assertSessionHasErrors([
        'file' => 'Er is geen geldig tijdelijk Excel-importbestand beschikbaar.',
    ]);
});

test('een beheerder kan geen Excel-voorbeeldweergave van een andere catalogus openen', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak twee verschillende catalogussen aan.
    $catalogA = Catalog::create([
        'name' => 'Testcatalogus A',
    ]);

    $catalogB = Catalog::create([
        'name' => 'Testcatalogus B',
    ]);

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = 'catalog-edition-imports/testbestand.xlsx';

    Storage::disk('local')->put(
        $filePath,
        'Tijdelijke testinhoud'
    );

    // Open de voorbeeldweergave van catalogus B
    // met importgegevens die bij catalogus A horen.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalogA->id,
                'file_path' => $filePath,
            ],
        ])
        ->get(
            route('backend.catalogs.editions.import.preview', $catalogB)
        );

    // Controleer of de beheerder wordt teruggestuurd.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.create', $catalogB)
    );

    // Controleer of de juiste foutmelding wordt gegeven.
    $response->assertSessionHasErrors([
        'file' => 'Er is geen geldig tijdelijk Excel-importbestand beschikbaar.',
    ]);

    // Controleer of het tijdelijke bestand behouden blijft.
    Storage::disk('local')->assertExists($filePath);
});

test('een beheerder kan geen Excel-voorbeeldweergave van een andere beheerder openen', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak twee beheerders aan.
    $adminA = User::factory()->create([
        'is_admin' => true,
    ]);

    $adminB = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus toegangscontrole beheerders',
    ]);

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = 'catalog-edition-imports/testbestand.xlsx';

    Storage::disk('local')->put(
        $filePath,
        'Tijdelijke testinhoud'
    );

    // Beheerder B probeert importgegevens van beheerder A te gebruiken.
    $response = $this
        ->actingAs($adminB)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $adminA->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->get(
            route('backend.catalogs.editions.import.preview', $catalog)
        );

    // Controleer of de toegang wordt geweigerd.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.create', $catalog)
    );

    // Controleer de foutmelding.
    $response->assertSessionHasErrors([
        'file' => 'Er is geen geldig tijdelijk Excel-importbestand beschikbaar.',
    ]);

    // Controleer of het tijdelijke bestand behouden blijft.
    Storage::disk('local')->assertExists($filePath);
});

test('een beheerder kan geen Excel-voorbeeldweergave openen als het tijdelijke bestand ontbreekt', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend importbestand',
    ]);

    // Verwijs naar een tijdelijk bestand dat niet bestaat.
    $filePath = 'catalog-edition-imports/ontbrekend-bestand.xlsx';

    // Controleer vooraf dat het bestand ontbreekt.
    Storage::disk('local')->assertMissing($filePath);

    // Probeer de voorbeeldweergave te openen.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->get(
            route('backend.catalogs.editions.import.preview', $catalog)
        );

    // Controleer of de beheerder wordt teruggestuurd.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.create', $catalog)
    );

    // Controleer de foutmelding.
    $response->assertSessionHasErrors([
        'file' => 'Er is geen geldig tijdelijk Excel-importbestand beschikbaar.',
    ]);
});

test('een beheerder kan geldige tijdelijke Excel-importgegevens controleren', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geldige tijdelijke import',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie preview',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    // Sla het Excel-bestand tijdelijk en privé op.
    $filePath = 'catalog-edition-imports/geldig-testbestand.xlsx';

    Storage::disk('local')->put(
        $filePath,
        $excelContent
    );

    // Controleer of het bestand bestaat.
    Storage::disk('local')->assertExists($filePath);

    // Open de voorbeeldweergave met geldige sessiegegevens.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->get(
            route('backend.catalogs.editions.import.preview', $catalog)
        );

    // Controleer of de voorbeeldpagina succesvol wordt weergegeven.
    $response->assertOk();

    // Controleer of de juiste Blade-pagina wordt gebruikt.
    $response->assertViewIs('backend.catalog-editions.import-preview');

    // Controleer of de juiste catalogus wordt doorgegeven.
    $response->assertViewHas('catalog', $catalog);

    // Controleer of de bestaande cataloguseditie wordt herkend.
    $response->assertViewHas('existingEdition', function ($existingEdition) use ($edition) {
        return $existingEdition !== null
            && $existingEdition->id === $edition->id;
    });

    // Controleer of Aanvullen standaard geselecteerd is.
    $response->assertSee(
        'name="import_mode" value="merge" checked',
        false
    );

    // Controleer de gegevens uit het Excel-bestand.
    $response->assertViewHas('preview', function ($preview) {
        return $preview['catalog_name'] === 'Testcatalogus geldige tijdelijke import'
            && $preview['edition_name'] === 'Testeditie preview'
            && $preview['edition_year'] === 2026
            && $preview['country_count'] === 0
            && $preview['series_count'] === 0
            && $preview['stamp_count'] === 0
            && $preview['theme_count'] === 0;
    });

    // Controleer of er geen validatiefouten zijn.
    $response->assertSessionHasNoErrors();

    // Controleer of het tijdelijke bestand behouden blijft.
    Storage::disk('local')->assertExists($filePath);
});

test('een beheerder kan een bestaande cataloguseditie niet opnieuw importeren', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele import',
    ]);

    // Maak een bestaande cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-bestand van deze editie.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    // Sla het Excel-bestand tijdelijk op.
    $filePath = 'catalog-edition-imports/dubbele-editie.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    // Probeer de bestaande editie opnieuw te importeren.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog)
        );

    // De gebruiker keert terug naar de voorbeeldpagina.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.preview', $catalog)
    );

    // De import moet een foutmelding geven.
    $response->assertSessionHasErrors('file');

    // Er mag geen tweede editie zijn aangemaakt.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->where('edition_name', 'Editie 2026')
            ->where('edition_year', 2026)
            ->count()
    )->toBe(1);

    // Het tijdelijke bestand blijft beschikbaar.
    Storage::disk('local')->assertExists($filePath);
});

test('een beheerder kan een nieuwe cataloguseditie definitief importeren', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus succesvolle import',
    ]);

    // Maak een cataloguseditie aan als bron voor de Excel-export.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Nieuwe editie 2027',
        'edition_year' => 2027,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    // Verwijder de broneditie, zodat deze opnieuw geïmporteerd kan worden.
    $edition->delete();

    // Sla het Excel-bestand tijdelijk en privé op.
    $filePath = 'catalog-edition-imports/nieuwe-editie.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    // Bevestig de definitieve Excel-import.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog)
        );

    // Controleer of de nieuwe cataloguseditie is aangemaakt.
    $importedEdition = CatalogEdition::query()
        ->where('catalog_id', $catalog->id)
        ->where('edition_name', 'Nieuwe editie 2027')
        ->where('edition_year', 2027)
        ->first();

    expect($importedEdition)->not->toBeNull();

    // Controleer of de gebruiker naar de nieuwe editie wordt doorgestuurd.
    $response->assertRedirect(
        route('backend.catalogs.editions.show', [
            'catalog' => $catalog,
            'edition' => $importedEdition,
        ])
    );

    // Controleer de succesmelding.
    $response->assertSessionHas(
        'success',
        'De cataloguseditie is succesvol geïmporteerd.'
    );

    // Controleer of het tijdelijke Excel-bestand is verwijderd.
    Storage::disk('local')->assertMissing($filePath);

    // Controleer of de tijdelijke importgegevens uit de sessie zijn verwijderd.
    $response->assertSessionMissing('catalog_edition_import');

    // Controleer dat er precies één editie met deze gegevens bestaat.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->where('edition_name', 'Nieuwe editie 2027')
            ->where('edition_year', 2027)
            ->count()
    )->toBe(1);
});

test('een gewone gebruiker kan geen definitieve Excel-import bevestigen', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een gewone gebruiker aan.
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbevoegde importbevestiging',
    ]);

    // Maak een tijdelijk bestand aan.
    $filePath = 'catalog-edition-imports/onbevoegde-import.xlsx';

    Storage::disk('local')->put(
        $filePath,
        'Tijdelijke testinhoud'
    );

    // Probeer de definitieve import te bevestigen.
    $response = $this
        ->actingAs($user)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $user->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog)
        );

    // Een gewone gebruiker krijgt geen toegang.
    $response->assertForbidden();

    // Het tijdelijke bestand blijft behouden.
    Storage::disk('local')->assertExists($filePath);

    // Er mag geen cataloguseditie zijn aangemaakt.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->count()
    )->toBe(0);
});

test('een beheerder kan geen definitieve Excel-import bevestigen zonder tijdelijk bestand', function () {
    // Gebruik een geïsoleerde opslagomgeving.
    Storage::fake('local');

    // Maak een beheerder aan.
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bevestiging zonder bestand',
    ]);

    // Probeer de import te bevestigen zonder tijdelijke importgegevens.
    $response = $this
        ->actingAs($admin)
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog)
        );

    // De beheerder wordt teruggestuurd naar het importformulier.
    $response->assertRedirect(
        route('backend.catalogs.editions.import.create', $catalog)
    );

    // Controleer de foutmelding.
    $response->assertSessionHasErrors([
        'file' => 'Er is geen geldig tijdelijk Excel-importbestand beschikbaar.',
    ]);

    // Er mag geen cataloguseditie zijn aangemaakt.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->count()
    )->toBe(0);
});

test('een beheerder kan een bestaande cataloguseditie aanvullen via HTTP', function () {
    Storage::fake('local');

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus HTTP aanvullen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-bestand van de bestaande editie.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    $filePath = 'catalog-edition-imports/http-aanvullen.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    // Bevestig de import met de methode Aanvullen.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog),
            [
                'import_mode' => 'merge',
            ]
        );

    // Controleer of de bestaande editie is hergebruikt.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->where('edition_name', 'Editie 2026')
            ->where('edition_year', 2026)
            ->count()
    )->toBe(1);

    // Controleer de doorverwijzing.
    $response->assertRedirect(
        route('backend.catalogs.editions.show', [
            'catalog' => $catalog,
            'edition' => $edition,
        ])
    );

    // Controleer de succesmelding.
    $response->assertSessionHas(
        'success',
        'De cataloguseditie is succesvol geïmporteerd.'
    );

    // Controleer of het tijdelijke bestand is verwijderd.
    Storage::disk('local')->assertMissing($filePath);

    // Controleer of de importsessie is opgeruimd.
    $response->assertSessionMissing('catalog_edition_import');
});

test('een beheerder kan een bestaande cataloguseditie vervangen via HTTP', function () {
    Storage::fake('local');

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus HTTP vervangen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-bestand van de bestaande editie.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    $filePath = 'catalog-edition-imports/http-vervangen.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    // Bevestig de import met de methode Vervangen.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog),
            [
                'import_mode' => 'replace',
            ]
        );

    // Controleer dat de bestaande editie behouden blijft.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->where('edition_name', 'Editie 2026')
            ->where('edition_year', 2026)
            ->count()
    )->toBe(1);

    // Controleer de doorverwijzing.
    $response->assertRedirect(
        route('backend.catalogs.editions.show', [
            'catalog' => $catalog,
            'edition' => $edition,
        ])
    );

    // Controleer de succesmelding.
    $response->assertSessionHas(
        'success',
        'De cataloguseditie is succesvol geïmporteerd.'
    );

    // Controleer of het tijdelijke bestand is verwijderd.
    Storage::disk('local')->assertMissing($filePath);

    // Controleer of de importsessie is opgeruimd.
    $response->assertSessionMissing('catalog_edition_import');
});

test('een beheerder kan geen ongeldige importmethode gebruiken', function () {
    Storage::fake('local');

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige importmethode',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    $filePath = 'catalog-edition-imports/ongeldige-importmethode.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog),
            [
                'import_mode' => 'delete_all',
            ]
        );

    // De controller moet de ongeldige importmethode weigeren.
    $response->assertSessionHasErrors('import_mode');

    // De bestaande cataloguseditie blijft behouden.
    expect(
        CatalogEdition::query()
            ->where('catalog_id', $catalog->id)
            ->where('edition_name', 'Editie 2026')
            ->where('edition_year', 2026)
            ->count()
    )->toBe(1);

    // Het tijdelijke Excel-bestand blijft beschikbaar.
    Storage::disk('local')->assertExists($filePath);
});
