<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use App\Models\Theme;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class ThemesSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'theme_slug',
                'name',
                'description',
            ],
        ];

        $themeIds = $this->edition->stampEntries()
            ->with([
                'stamp.themes',
                'stamp.parentStamp.themes',
            ])
            ->get()
            ->flatMap(function ($entry) {
                // Verzamel de thema-ID's van de postzegel zelf.
                $stampThemeIds = $entry->stamp?->themes->pluck('id') ?? collect();

                // Verzamel de thema-ID's van de bovenliggende postzegel.
                $parentThemeIds = $entry->stamp?->parentStamp?->themes->pluck('id') ?? collect();

                // Voeg beide verzamelingen samen.
                return $stampThemeIds->merge($parentThemeIds);
            })
            ->unique()
            ->values();

        $themes = Theme::query()
            ->whereIn('id', $themeIds)
            ->orderBy('slug')
            ->get();

        foreach ($themes as $theme) {
            $rows[] = [
                $theme->slug,
                $theme->name,
                $theme->description,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Themas';
    }
}
