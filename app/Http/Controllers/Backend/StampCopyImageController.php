<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StampCopy;
use App\Models\StampCopyImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StampCopyImageController extends Controller
{
    public function store(Request $request, StampCopy $stampCopy): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_type' => ['required', 'in:front,back,detail'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        // Laad de postzegel en het bijbehorende land.
        $stampCopy->load('stamp.country');

        // Persoonlijke afbeeldingen gescheiden opslaan van catalogusafbeeldingen.
        $directory = 'stamp-copies/'
            .$stampCopy->stamp->country->slug.'/'
            .$stampCopy->stamp->issue_year.'/'
            .$stampCopy->id;

        // Sla het bestand op in Laravel Storage.
        $path = $validated['image']->store($directory, 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => __('De afbeelding kon niet worden opgeslagen. Probeer het opnieuw.'),
            ]);
        }

        try {
            // Maak de databasevermelding aan.
            $stampCopy->images()->create([
                'path' => $path,
                'image_type' => $validated['image_type'],
                'alt_text' => $validated['alt_text'] ?? null,
                'is_primary' => false,
            ]);
        } catch (\Throwable $exception) {
            // Verwijder het bestand wanneer opslaan in MariaDB mislukt.
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return redirect()
            ->route('backend.stamp-copies.edit', $stampCopy)
            ->with('success', __('De afbeelding is succesvol geüpload.'));
    }

    /**
     * Update an image belonging to a personal stamp copy.
     */
    public function update(
        Request $request,
        StampCopy $stampCopy,
        StampCopyImage $image
    ): RedirectResponse {
        // Controleer of de afbeelding bij dit postzegelexemplaar hoort.
        abort_unless($image->stamp_copy_id === $stampCopy->id, 404);

        $validated = $request->validate([
            'image_type' => ['required', 'in:front,back,detail'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldPath = $image->path;
        $newPath = null;

        // Alleen een nieuw bestand opslaan wanneer er een afbeelding is geselecteerd.
        if ($request->hasFile('image')) {

            $stampCopy->load('stamp.country');

            $directory = 'stamp-copies/'
                .$stampCopy->stamp->country->slug.'/'
                .$stampCopy->stamp->issue_year.'/'
                .$stampCopy->id;

            $newPath = $request->file('image')->store($directory, 'public');

            if ($newPath === false) {
                throw ValidationException::withMessages([
                    'image' => __('De nieuwe afbeelding kon niet worden opgeslagen.'),
                ]);
            }

            $validated['path'] = $newPath;
        }

        // Het uploadbestand hoort niet rechtstreeks in de database.
        unset($validated['image']);

        try {
            $image->update($validated);
        } catch (\Throwable $exception) {

            // Nieuwe upload opruimen als de databasewijziging mislukt.
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        // Oude afbeelding pas verwijderen nadat de database is bijgewerkt.
        if ($newPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()
            ->route('backend.stamp-copies.edit', $stampCopy)
            ->with('success', __('De afbeeldingsgegevens zijn bijgewerkt.'));
    }

    /**
     * Remove an image belonging to a personal stamp copy.
     */
    public function destroy(
        StampCopy $stampCopy,
        StampCopyImage $image
    ): RedirectResponse {
        // Controleer of de afbeelding bij dit postzegelexemplaar hoort.
        abort_unless($image->stamp_copy_id === $stampCopy->id, 404);

        // Bewaar het bestandspad voordat de databasevermelding wordt verwijderd.
        $path = $image->path;

        // Verwijder de databasevermelding.
        $image->delete();

        // Verwijder het afbeeldingsbestand uit Laravel Storage.
        Storage::disk('public')->delete($path);

        return redirect()
            ->route('backend.stamp-copies.edit', $stampCopy)
            ->with('success', __('De afbeelding is succesvol verwijderd.'));
    }
}
