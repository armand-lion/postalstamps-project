<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Display a listing of the catalogs.
     */
    public function index(Request $request): View
    {
        $query = Catalog::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('abbreviation', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%");
            });
        }

        $catalogs = $query
            ->withCount(['countries', 'editions'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('backend.catalogs.index', [
            'catalogs' => $catalogs,
        ]);
    }

    /**
     * Show the form for creating a new catalog.
     */
    public function create(): View
    {
        return view('backend.catalogs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Catalog $catalog): View
    {
        $catalog->load([
            'countries.translations',
            'editions',
        ]);

        return view('backend.catalogs.show', [
            'catalog' => $catalog,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Catalog $catalog): View
    {
        $catalog->load([
            'countries.translations',
        ]);

        return view('backend.catalogs.edit', [
            'catalog' => $catalog,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Catalog $catalog): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Catalog $catalog): void
    {
        //
    }
}
