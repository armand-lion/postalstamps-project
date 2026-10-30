<?php

namespace Tests\Feature\Backend;

use App\Models\Stamp;
use App\Models\StampCopy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StampCopyImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_een_afbeelding_kan_worden_toegevoegd_aan_een_verzamelexemplaar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $stampCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(
            route('backend.stamp-copies.images.store', $stampCopy),
            [
                'image' => UploadedFile::fake()->image('mijn-postzegel.jpg'),
                'image_type' => 'front',
                'alt_text' => 'Mijn persoonlijke postzegel',
            ]
        );

        $response->assertRedirect(
            route('backend.stamp-copies.edit', $stampCopy)
        );

        $this->assertDatabaseHas('stamp_copy_images', [
            'stamp_copy_id' => $stampCopy->id,
            'image_type' => 'front',
            'alt_text' => 'Mijn persoonlijke postzegel',
        ]);

        $storedImage = $stampCopy->images()->firstOrFail();

        Storage::disk('public')->assertExists($storedImage->path);

        $this->assertStringStartsWith(
            'stamp-copies/'
                .$stamp->country->slug.'/'
                .$stamp->issue_year.'/'
                .$stampCopy->id.'/',
            $storedImage->path
        );
    }

    public function test_een_afbeelding_van_een_verzamelexemplaar_kan_worden_vervangen(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $stampCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        // Bestaande afbeelding opslaan.
        $oldPath = UploadedFile::fake()
            ->image('oude-afbeelding.jpg')
            ->store('stamp-copies', 'public');

        $stampCopyImage = $stampCopy->images()->create([
            'path' => $oldPath,
            'image_type' => 'front',
            'alt_text' => 'Oude afbeelding',
            'is_primary' => false,
        ]);

        Storage::disk('public')->assertExists($oldPath);

        // Bestaande afbeelding vervangen.
        $response = $this->actingAs($user)->put(
            route('backend.stamp-copies.images.update', [
                $stampCopy,
                $stampCopyImage,
            ]),
            [
                'image' => UploadedFile::fake()->image('nieuwe-afbeelding.jpg'),
                'image_type' => 'back',
                'alt_text' => 'Nieuwe achterzijde',
            ]
        );

        $response->assertRedirect(
            route('backend.stamp-copies.edit', $stampCopy)
        );

        $response->assertSessionHasNoErrors();

        $stampCopyImage->refresh();

        // De database moet naar de nieuwe afbeelding verwijzen.
        $this->assertDatabaseHas('stamp_copy_images', [
            'id' => $stampCopyImage->id,
            'image_type' => 'back',
            'alt_text' => 'Nieuwe achterzijde',
            'path' => $stampCopyImage->path,
        ]);

        // De nieuwe afbeelding moet bestaan.
        Storage::disk('public')->assertExists($stampCopyImage->path);

        // De nieuwe afbeelding moet in de juiste map staan.
        $this->assertStringStartsWith(
            'stamp-copies/'
                .$stamp->country->slug.'/'
                .$stamp->issue_year.'/'
                .$stampCopy->id.'/',
            $stampCopyImage->path
        );

        // De oude afbeelding moet verwijderd zijn.
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_een_afbeelding_van_een_verzamelexemplaar_kan_worden_verwijderd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $stampCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        // Afbeelding opslaan.
        $path = UploadedFile::fake()
            ->image('mijn-postzegel.jpg')
            ->store('stamp-copies', 'public');

        $stampCopyImage = $stampCopy->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Mijn postzegel',
            'is_primary' => false,
        ]);

        Storage::disk('public')->assertExists($path);

        // Afbeelding verwijderen via de backend.
        $response = $this->actingAs($user)->delete(
            route('backend.stamp-copies.images.destroy', [
                $stampCopy,
                $stampCopyImage,
            ])
        );

        $response->assertRedirect(
            route('backend.stamp-copies.edit', $stampCopy)
        );

        $response->assertSessionHas('success');

        // De databasevermelding moet verwijderd zijn.
        $this->assertDatabaseMissing('stamp_copy_images', [
            'id' => $stampCopyImage->id,
        ]);

        // Het afbeeldingsbestand moet verwijderd zijn.
        Storage::disk('public')->assertMissing($path);

        // Het verzamelexemplaar zelf moet behouden blijven.
        $this->assertDatabaseHas('stamp_copies', [
            'id' => $stampCopy->id,
        ]);
    }

    public function test_een_afbeelding_van_een_ander_verzamelexemplaar_kan_niet_worden_gewijzigd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $firstCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        $secondCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        // Afbeelding koppelen aan het eerste verzamelexemplaar.
        $path = UploadedFile::fake()
            ->image('origineel.jpg')
            ->store('stamp-copies', 'public');

        $image = $firstCopy->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Originele afbeelding',
            'is_primary' => false,
        ]);

        // Proberen de afbeelding via het tweede exemplaar te wijzigen.
        $response = $this->actingAs($user)->put(
            route('backend.stamp-copies.images.update', [
                $secondCopy,
                $image,
            ]),
            [
                'image_type' => 'back',
                'alt_text' => 'Gewijzigde afbeelding',
            ]
        );

        // De controller moet het verzoek weigeren.
        $response->assertNotFound();

        // De oorspronkelijke gegevens moeten behouden blijven.
        $this->assertDatabaseHas('stamp_copy_images', [
            'id' => $image->id,
            'stamp_copy_id' => $firstCopy->id,
            'image_type' => 'front',
            'alt_text' => 'Originele afbeelding',
            'path' => $path,
        ]);

        // Het oorspronkelijke afbeeldingsbestand moet blijven bestaan.
        Storage::disk('public')->assertExists($path);
    }

    public function test_een_afbeelding_van_een_ander_verzamelexemplaar_kan_niet_worden_verwijderd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $firstCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        $secondCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        // Afbeelding koppelen aan het eerste verzamelexemplaar.
        $path = UploadedFile::fake()
            ->image('origineel.jpg')
            ->store('stamp-copies', 'public');

        $image = $firstCopy->images()->create([
            'path' => $path,
            'image_type' => 'front',
            'alt_text' => 'Originele afbeelding',
            'is_primary' => false,
        ]);

        // Proberen de afbeelding via het tweede exemplaar te verwijderen.
        $response = $this->actingAs($user)->delete(
            route('backend.stamp-copies.images.destroy', [
                $secondCopy,
                $image,
            ])
        );

        // De controller moet het verzoek weigeren.
        $response->assertNotFound();

        // De databasevermelding moet behouden blijven.
        $this->assertDatabaseHas('stamp_copy_images', [
            'id' => $image->id,
            'stamp_copy_id' => $firstCopy->id,
            'path' => $path,
        ]);

        // Het afbeeldingsbestand moet blijven bestaan.
        Storage::disk('public')->assertExists($path);
    }

    public function test_een_ongeldig_afbeeldingsbestand_wordt_geweigerd(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $stamp = Stamp::factory()->create();

        $stampCopy = StampCopy::create([
            'stamp_id' => $stamp->id,
            'quantity' => 1,
        ]);

        // Een tekstbestand aanbieden in plaats van een afbeelding.
        $response = $this->actingAs($user)->post(
            route('backend.stamp-copies.images.store', $stampCopy),
            [
                'image' => UploadedFile::fake()->create(
                    'ongeldig.txt',
                    10,
                    'text/plain'
                ),
                'image_type' => 'front',
                'alt_text' => 'Ongeldig bestand',
            ]
        );

        // Laravel moet het bestand weigeren.
        $response->assertSessionHasErrors(['image']);

        // Er mag geen afbeelding in de database zijn opgeslagen.
        $this->assertDatabaseCount('stamp_copy_images', 0);

        // Er mogen geen bestanden zijn opgeslagen.
        $this->assertSame(
            [],
            Storage::disk('public')->allFiles()
        );
    }
}
