<?php

namespace Tests\Feature\Backend;

use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StampImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_een_afbeelding_kan_worden_geupload(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $image = UploadedFile::fake()->image('postzegel.jpg');

        $response = $this->actingAs($user)->post(
            route('backend.stamps.images.store', $stamp),
            [
                'image' => $image,
                'image_type' => 'front',
                'alt_text' => 'Voorzijde van de postzegel',
            ]
        );

        $response->assertRedirect(
            route('backend.stamps.show', $stamp)
        );

        $this->assertDatabaseHas('stamp_images', [
            'stamp_id' => $stamp->id,
            'image_type' => 'front',
            'alt_text' => 'Voorzijde van de postzegel',
        ]);

        $storedImage = $stamp->images()->firstOrFail();

        Storage::disk('public')->assertExists($storedImage->path);
    }

    public function test_een_afbeelding_kan_worden_vervangen(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        // Bestaande afbeelding opslaan.
        $oldPath = UploadedFile::fake()
            ->image('oude-postzegel.jpg')
            ->store('stamps', 'public');

        $stampImage = $stamp->images()->create([
            'path' => $oldPath,
            'image_type' => 'front',
            'alt_text' => 'Oude afbeelding',
            'is_primary' => false,
        ]);

        Storage::disk('public')->assertExists($oldPath);

        // Afbeelding vervangen en gegevens tegelijk wijzigen.
        $response = $this->actingAs($user)->patch(
            route('backend.stamps.images.update', [$stamp, $stampImage]),
            [
                'image' => UploadedFile::fake()->image('nieuwe-postzegel.jpg'),
                'image_type' => 'back',
                'alt_text' => 'Nieuwe achterzijde',
            ]
        );

        $response->assertRedirect(
            route('backend.stamps.edit', $stamp)
        );

        $stampImage->refresh();

        // Controleren of de nieuwe afbeelding is opgeslagen.
        Storage::disk('public')->assertExists($stampImage->path);

        // Controleren of de oude afbeelding is verwijderd.
        Storage::disk('public')->assertMissing($oldPath);

        // Controleren of de overige gegevens zijn bijgewerkt.
        $this->assertSame('back', $stampImage->image_type);
        $this->assertSame('Nieuwe achterzijde', $stampImage->alt_text);

        // De bestaande afbeeldingsrecord moet behouden blijven.
        $this->assertDatabaseCount('stamp_images', 1);
    }

    public function test_afbeeldingsgegevens_kunnen_worden_gewijzigd_zonder_afbeelding_te_vervangen(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $originalPath = UploadedFile::fake()
            ->image('originele-postzegel.jpg')
            ->store('stamps', 'public');

        $stampImage = $stamp->images()->create([
            'path' => $originalPath,
            'image_type' => 'front',
            'alt_text' => 'Oorspronkelijke beschrijving',
            'is_primary' => false,
        ]);

        // Alleen het afbeeldingstype en de beschrijving wijzigen.
        $response = $this->actingAs($user)->patch(
            route('backend.stamps.images.update', [$stamp, $stampImage]),
            [
                'image_type' => 'detail',
                'alt_text' => 'Bijgewerkte beschrijving',
            ]
        );

        $response->assertRedirect(
            route('backend.stamps.edit', $stamp)
        );

        $stampImage->refresh();

        // Het oorspronkelijke afbeeldingsbestand moet behouden blijven.
        $this->assertSame($originalPath, $stampImage->path);
        Storage::disk('public')->assertExists($originalPath);

        // Alleen de afbeeldingsgegevens moeten zijn gewijzigd.
        $this->assertSame('detail', $stampImage->image_type);
        $this->assertSame('Bijgewerkte beschrijving', $stampImage->alt_text);

        // Er mag geen extra afbeeldingsrecord zijn aangemaakt.
        $this->assertDatabaseCount('stamp_images', 1);
    }

    public function test_een_afbeelding_kan_worden_verwijderd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $path = UploadedFile::fake()
            ->image('te-verwijderen-postzegel.jpg')
            ->store('stamps', 'public');

        $stampImage = $stamp->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Afbeelding die verwijderd wordt',
            'is_primary' => false,
        ]);

        Storage::disk('public')->assertExists($path);

        // De afbeelding verwijderen via de backend.
        $response = $this->actingAs($user)->delete(
            route('backend.stamps.images.destroy', [$stamp, $stampImage])
        );

        $response->assertRedirect(
            route('backend.stamps.edit', $stamp)
        );

        // Het bestand moet daadwerkelijk verwijderd zijn.
        Storage::disk('public')->assertMissing($path);

        // Ook de databasevermelding moet verwijderd zijn.
        $this->assertDatabaseMissing('stamp_images', [
            'id' => $stampImage->id,
        ]);

        $this->assertDatabaseCount('stamp_images', 0);

        // De postzegel zelf moet blijven bestaan.
        $this->assertDatabaseHas('stamps', [
            'id' => $stamp->id,
        ]);
    }

    public function test_een_afbeelding_van_een_andere_postzegel_kan_niet_worden_gewijzigd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stampA = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $stampB = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $path = UploadedFile::fake()
            ->image('postzegel-b.jpg')
            ->store('stamps', 'public');

        $image = $stampB->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Afbeelding van postzegel B',
            'is_primary' => false,
        ]);

        // Proberen de afbeelding van B te wijzigen via postzegel A.
        $response = $this->actingAs($user)->patch(
            route('backend.stamps.images.update', [$stampA, $image]),
            [
                'image_type' => 'back',
                'alt_text' => 'Ongeoorloofde wijziging',
            ]
        );

        // Laravel moet dit weigeren.
        $response->assertNotFound();

        $image->refresh();

        // De oorspronkelijke gegevens moeten behouden blijven.
        $this->assertSame('front', $image->image_type);
        $this->assertSame('Afbeelding van postzegel B', $image->alt_text);

        // Ook het bestand moet behouden blijven.
        Storage::disk('public')->assertExists($path);
    }

    public function test_een_afbeelding_van_een_andere_postzegel_kan_niet_worden_verwijderd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stampA = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $stampB = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $path = UploadedFile::fake()
            ->image('postzegel-b.jpg')
            ->store('stamps', 'public');

        $image = $stampB->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Afbeelding van postzegel B',
            'is_primary' => false,
        ]);

        // Proberen de afbeelding van B te verwijderen via postzegel A.
        $response = $this->actingAs($user)->delete(
            route('backend.stamps.images.destroy', [$stampA, $image])
        );

        // Laravel moet het verzoek weigeren.
        $response->assertNotFound();

        // De afbeelding moet in de database blijven bestaan.
        $this->assertDatabaseHas('stamp_images', [
            'id' => $image->id,
            'stamp_id' => $stampB->id,
            'path' => $path,
        ]);

        // Ook het afbeeldingsbestand moet behouden blijven.
        Storage::disk('public')->assertExists($path);

        // Er mag geen afbeelding verwijderd zijn.
        $this->assertDatabaseCount('stamp_images', 1);
    }

    public function test_een_ongeldig_afbeeldingsbestand_wordt_geweigerd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        // Een tekstbestand is geen toegestane afbeelding.
        $invalidFile = UploadedFile::fake()->create(
            'geen-afbeelding.txt',
            10,
            'text/plain'
        );

        $response = $this->actingAs($user)->post(
            route('backend.stamps.images.store', $stamp),
            [
                'image' => $invalidFile,
                'image_type' => 'front',
                'alt_text' => 'Ongeldig bestand',
            ]
        );

        // Laravel moet een validatiefout teruggeven.
        $response->assertSessionHasErrors('image');

        // Er mag geen afbeeldingsrecord worden aangemaakt.
        $this->assertDatabaseCount('stamp_images', 0);

        // Er mogen geen bestanden zijn opgeslagen.
        $this->assertSame([], Storage::disk('public')->allFiles('stamps'));
    }

    public function test_een_te_grote_afbeelding_wordt_geweigerd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        // Een geldige JPG-afbeelding die groter is dan 5 MB.
        $largeImage = UploadedFile::fake()
            ->image('grote-postzegel.jpg')
            ->size(6144);

        $response = $this->actingAs($user)->post(
            route('backend.stamps.images.store', $stamp),
            [
                'image' => $largeImage,
                'image_type' => 'front',
                'alt_text' => 'Te grote afbeelding',
            ]
        );

        // Laravel moet de afbeelding weigeren.
        $response->assertSessionHasErrors('image');

        // Er mag geen afbeeldingsrecord zijn aangemaakt.
        $this->assertDatabaseCount('stamp_images', 0);

        // Er mogen geen bestanden zijn opgeslagen.
        $this->assertSame(
            [],
            Storage::disk('public')->allFiles('stamps')
        );
    }

    public function test_een_ongeldige_vervangende_afbeelding_wordt_geweigerd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
        ]);

        $originalPath = UploadedFile::fake()
            ->image('originele-postzegel.jpg')
            ->store('stamps', 'public');

        $stampImage = $stamp->images()->create([
            'path' => $originalPath,
            'image_type' => 'front',
            'alt_text' => 'Oorspronkelijke beschrijving',
            'is_primary' => false,
        ]);

        // Proberen de afbeelding te vervangen door een tekstbestand.
        $response = $this->actingAs($user)->patch(
            route('backend.stamps.images.update', [$stamp, $stampImage]),
            [
                'image' => UploadedFile::fake()->create(
                    'ongeldig.txt',
                    10,
                    'text/plain'
                ),
                'image_type' => 'back',
                'alt_text' => 'Gewijzigde beschrijving',
            ]
        );

        // Laravel moet het ongeldige bestand weigeren.
        $response->assertSessionHasErrors('image');

        $stampImage->refresh();

        // De oorspronkelijke afbeelding moet behouden blijven.
        $this->assertSame($originalPath, $stampImage->path);
        Storage::disk('public')->assertExists($originalPath);

        // Ook de andere gegevens mogen niet gewijzigd zijn.
        $this->assertSame('front', $stampImage->image_type);
        $this->assertSame(
            'Oorspronkelijke beschrijving',
            $stampImage->alt_text
        );

        // Er mag geen extra bestand zijn opgeslagen.
        $this->assertSame(
            [$originalPath],
            Storage::disk('public')->allFiles('stamps')
        );

        $this->assertDatabaseCount('stamp_images', 1);
    }

    public function test_een_postzegel_kan_met_een_afbeelding_worden_aangemaakt(): void
    {
        Storage::fake('public');

        app()->setLocale('nl');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $translations = [
            'nl' => [
                'name' => 'Nederlandse testzegel',
                'description' => 'Nederlandse beschrijving',
            ],
            'en' => [
                'name' => 'English test stamp',
                'description' => 'English description',
            ],
            'de' => [
                'name' => 'Deutsche Testbriefmarke',
                'description' => 'Deutsche Beschreibung',
            ],
            'fr' => [
                'name' => 'Timbre de test français',
                'description' => 'Description française',
            ],
            'es' => [
                'name' => 'Sello de prueba español',
                'description' => 'Descripción española',
            ],
        ];

        $response = $this->actingAs($user)->post(
            route('backend.stamps.store'),
            [
                'country_id' => $country->id,
                'issue_year' => 2026,
                'translations' => $translations,
                'image' => UploadedFile::fake()->image('postzegel.jpg'),
                'image_type' => 'front',
                'alt_text' => 'Voorzijde van de Nederlandse testzegel',
            ]
        );

        $response->assertRedirect(route('backend.stamps.index'));
        $response->assertSessionHasNoErrors();

        // Controleer of de postzegel is aangemaakt.
        $stamp = Stamp::query()
            ->where('country_id', $country->id)
            ->firstOrFail();

        $this->assertSame('Nederlandse testzegel', $stamp->name);

        // Controleer of alle vijf vertalingen zijn opgeslagen.
        $this->assertCount(5, $stamp->translations);

        // Controleer of de afbeelding aan de postzegel is gekoppeld.
        $this->assertDatabaseHas('stamp_images', [
            'stamp_id' => $stamp->id,
            'image_type' => 'front',
            'alt_text' => 'Voorzijde van de Nederlandse testzegel',
        ]);

        $storedImage = $stamp->images()->firstOrFail();

        // Controleer of het afbeeldingsbestand daadwerkelijk bestaat.
        Storage::disk('public')->assertExists($storedImage->path);

        // Er mag maar één afbeelding zijn aangemaakt.
        $this->assertDatabaseCount('stamp_images', 1);
    }

    public function test_een_postzegel_wordt_niet_aangemaakt_met_een_ongeldige_afbeelding(): void
    {
        Storage::fake('public');

        app()->setLocale('nl');

        $user = User::factory()->create();
        $country = Country::factory()->create();

        $response = $this->actingAs($user)->post(
            route('backend.stamps.store'),
            [
                'country_id' => $country->id,
                'issue_year' => 2026,
                'translations' => [
                    'nl' => [
                        'name' => 'Postzegel met ongeldige afbeelding',
                        'description' => 'Deze postzegel mag niet worden opgeslagen.',
                    ],
                ],
                'image' => UploadedFile::fake()->create(
                    'ongeldig.pdf',
                    100,
                    'application/pdf'
                ),
                'image_type' => 'front',
            ]
        );

        // Laravel moet het ongeldige bestand weigeren.
        $response->assertSessionHasErrors('image');

        // De postzegel mag niet worden aangemaakt.
        $this->assertDatabaseMissing('stamps', [
            'country_id' => $country->id,
            'name' => 'Postzegel met ongeldige afbeelding',
        ]);

        // Er mag geen afbeeldingsrecord bestaan.
        $this->assertDatabaseCount('stamp_images', 0);

        // Er mag geen afbeeldingsbestand zijn opgeslagen.
        $this->assertSame(
            [],
            Storage::disk('public')->allFiles('stamps')
        );
    }
}
