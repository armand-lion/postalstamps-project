<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\SeriesCatalogValue;
use App\Models\StampCatalogValue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConditionsSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'condition_slug',
                'sort_order',
                'is_active',
            ],
        ];

        $ruleIds = $this->edition->catalog
            ->conditionRules()
            ->pluck('condition_id');

        $valueIds = StampCatalogValue::query()
            ->whereHas('catalogEntry', function ($query) {
                $query->where('catalog_edition_id', $this->edition->id);
            })
            ->pluck('condition_id');

        // Waarderingscategorieën van postzegelseries binnen deze editie.
        $seriesValueIds = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $this->edition->id)
            ->pluck('condition_id');

        // Alle gebruikte waarderingscategorieën samenvoegen.
        $conditionIds = $ruleIds
            ->merge($valueIds)
            ->merge($seriesValueIds)
            ->filter()
            ->unique()
            ->values();

        $conditions = Condition::query()
            ->whereIn('id', $conditionIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($conditions as $condition) {
            $rows[] = [
                $condition->slug,
                $condition->sort_order,
                $condition->is_active ? 1 : 0,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Waarderingscategorieen';
    }
}
