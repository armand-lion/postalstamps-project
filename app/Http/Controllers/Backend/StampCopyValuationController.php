<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StampCopy;
use App\Models\StampCopyValuation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StampCopyValuationController extends Controller
{
    /**
     * Store a newly created valuation.
     */
    public function store(Request $request, StampCopy $stampCopy): RedirectResponse
    {
        // Alleen de eigenaar mag persoonlijke waarderingen toevoegen.
        abort_unless(
            $stampCopy->user_id === $request->user()->id,
            404
        );

        $validated = $request->validate([
            'valuation_date' => ['required', 'date'],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'source' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
        ]);

        $stampCopy->valuations()->create($validated);

        return redirect()
            ->route('backend.stamp-copies.show', $stampCopy)
            ->with('success', __('Waardering succesvol toegevoegd.'));
    }

    /**
     * Update an existing valuation.
     */
    public function update(
        Request $request,
        StampCopy $stampCopy,
        StampCopyValuation $valuation
    ): RedirectResponse {
        // Alleen de eigenaar mag persoonlijke waarderingen wijzigen.
        abort_unless(
            $stampCopy->user_id === $request->user()->id,
            404
        );

        // Controleer of de waardering bij dit postzegelexemplaar hoort.
        abort_unless(
            $valuation->stamp_copy_id === $stampCopy->id,
            404
        );

        $validated = $request->validate([
            'valuation_date' => ['required', 'date'],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'source' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
        ]);

        $valuation->update($validated);

        return redirect()
            ->route('backend.stamp-copies.show', $stampCopy)
            ->with('success', __('Waardering succesvol bijgewerkt.'));
    }

    /**
     * Remove an existing valuation.
     */
    public function destroy(
        StampCopy $stampCopy,
        StampCopyValuation $valuation
    ): RedirectResponse {
        // Alleen de eigenaar mag persoonlijke waarderingen verwijderen.
        abort_unless(
            $stampCopy->user_id === auth()->id(),
            404
        );

        // Controleer of de waardering bij dit postzegelexemplaar hoort.
        abort_unless(
            $valuation->stamp_copy_id === $stampCopy->id,
            404
        );

        $valuation->delete();

        return redirect()
            ->route('backend.stamp-copies.edit', $stampCopy)
            ->with('success', __('Waardering succesvol verwijderd.'));
    }
}
