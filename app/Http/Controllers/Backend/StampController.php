<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StampController extends Controller
{
    /**
     * Display a listing of the stamps.
     */
    public function index(Request $request): View
    {
        $query = Stamp::with(['country', 'series']);

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        if ($request->filled('issue_year')) {
            $query->where('issue_year', $request->integer('issue_year'));
        }

        $stamps = $query
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $countries = Country::orderBy('name')->get();

        return view('backend.stamps.index', compact(
            'stamps',
            'countries'
        ));
    }

    /**
     * Show the form for creating a stamp.
     */
    public function create(): View
    {
        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.create', compact(
            'countries',
            'series'
        ));
    }

    /**
     * Store a newly created stamp.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                'exists:stamp_series,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $stamp = Stamp::create($validated);

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is toegevoegd."
            );
    }

    /**
     * Display a stamp.
     */
    public function show(Stamp $stamp): View
    {
        $stamp->load([
            'country',
            'series',
            'themes',
            'copies.condition.translation',
            'images',
        ]);

        return view('backend.stamps.show', compact('stamp'));
    }

    /**
     * Show the form for editing a stamp.
     */
    public function edit(Stamp $stamp): View
    {
        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.edit', compact(
            'stamp',
            'countries',
            'series'
        ));
    }

    /**
     * Update a stamp.
     */
    public function update(
        Request $request,
        Stamp $stamp
    ): RedirectResponse {
        $validated = $request->validate([
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                'exists:stamp_series,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $stamp->update($validated);

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is bijgewerkt."
            );
    }

    /**
     * Delete a stamp.
     */
    public function destroy(Stamp $stamp): RedirectResponse
    {
        $name = $stamp->name;

        // Controleer of er gekoppelde gegevens bestaan.
        $hasRelatedData =
            $stamp->copies()->exists()
            || $stamp->images()->exists()
            || $stamp->catalogValues()->exists()
            || $stamp->valuations()->exists();

        // Verwijder de postzegel niet als er gegevens gekoppeld zijn.
        if ($hasRelatedData) {
            return redirect()
                ->route('backend.stamps.index')
                ->with(
                    'error',
                    "Postzegel '{$name}' kan niet worden verwijderd, omdat er nog gekoppelde gegevens bestaan."
                );
        }

        // Verwijder de koppelingen met thema's en daarna de postzegel.
        DB::transaction(function () use ($stamp) {
            $stamp->themes()->detach();
            $stamp->delete();
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$name}' is verwijderd."
            );
    }
}
