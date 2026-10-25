<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Stamp;
use App\Models\StampImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StampImageController extends Controller
{
    public function store(Request $request, Stamp $stamp): RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_type' => ['required', 'in:front,back,detail'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $validated['image']->store('stamps', 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => __('De afbeelding kon niet worden opgeslagen. Probeer het opnieuw.'),
            ]);
        }

        try {
            $stamp->images()->create([
                'path' => $path,
                'image_type' => $validated['image_type'],
                'alt_text' => $validated['alt_text'] ?? null,
                'is_primary' => false,
            ]);
        } catch (\Throwable $exception) {
            if ($path !== false) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        return redirect()
            ->route('backend.stamps.show', $stamp)
            ->with('success', __('De afbeelding is succesvol geüpload.'));
    }

    public function update(
        Request $request,
        Stamp $stamp,
        StampImage $image
    ): RedirectResponse {
        abort_unless($image->stamp_id === $stamp->id, 404);

        $validated = $request->validate([
            'image_type' => ['required', 'in:front,back,detail'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldPath = $image->path;
        $newPath = null;

        // Alleen een nieuw bestand opslaan als er een afbeelding is geselecteerd.
        if ($request->hasFile('image')) {
            $newPath = $request->file('image')->store('stamps', 'public');

            if ($newPath === false) {
                throw ValidationException::withMessages([
                    'image' => __('De nieuwe afbeelding kon niet worden opgeslagen.'),
                ]);
            }

            $validated['path'] = $newPath;
        }

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
            ->route('backend.stamps.edit', $stamp)
            ->with('success', __('De afbeeldingsgegevens zijn bijgewerkt.'));
    }

    public function destroy(Stamp $stamp, StampImage $image): RedirectResponse
    {
        // Controleer of de afbeelding bij deze postzegel hoort.
        abort_unless($image->stamp_id === $stamp->id, 404);

        // Verwijder eerst het afbeeldingsbestand.
        if (! Storage::disk('public')->delete($image->path)) {
            return redirect()
                ->route('backend.stamps.edit', $stamp)
                ->with('error', __('De afbeelding kon niet worden verwijderd.'));
        }

        // Verwijder vervolgens de databasevermelding.
        $image->delete();

        return redirect()
            ->route('backend.stamps.edit', $stamp)
            ->with('success', __('De afbeelding is succesvol verwijderd.'));
    }
}
