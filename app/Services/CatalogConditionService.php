<?php

namespace App\Services;

use App\Models\Catalog;
use App\Models\Condition;
use Illuminate\Database\Eloquent\Collection;

class CatalogConditionService
{
    /**
     * Bepaal welke waarderingscategorieën gelden voor een catalogus,
     * land en uitgiftejaar.
     *
     * @return Collection<int, Condition>
     */
    public function availableConditions(
        Catalog $catalog,
        ?int $countryId = null,
        ?int $issueYear = null
    ): Collection {
        // Bestaande catalogussen zonder configuratie gebruiken de standaard.
        if (! $catalog->conditionRules()->exists()) {
            return Condition::query()
                ->where('is_active', true)
                ->whereIn('slug', ['postfris', 'gestempeld'])
                ->orderBy('sort_order')
                ->get();
        }

        $rules = $catalog->conditionRules()
            ->where(function ($query) use ($countryId) {
                $query->whereNull('country_id');

                if ($countryId !== null) {
                    $query->orWhere('country_id', $countryId);
                }
            })
            ->where(function ($query) use ($issueYear) {
                if ($issueYear !== null) {
                    $query->where(function ($yearQuery) use ($issueYear) {
                        $yearQuery->whereNull('issue_year_from')
                            ->orWhere('issue_year_from', '<=', $issueYear);
                    });

                    $query->where(function ($yearQuery) use ($issueYear) {
                        $yearQuery->whereNull('issue_year_to')
                            ->orWhere('issue_year_to', '>=', $issueYear);
                    });
                } else {
                    // Zonder uitgiftejaar gelden alleen regels zonder periode.
                    $query->whereNull('issue_year_from')
                        ->whereNull('issue_year_to');
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $conditionIds = $rules
            ->pluck('condition_id')
            ->unique()
            ->values();

        return Condition::query()
            ->where('is_active', true)
            ->whereIn('id', $conditionIds)
            ->get()
            ->sortBy(function (Condition $condition) use ($conditionIds) {
                return $conditionIds->search($condition->id);
            })
            ->values();
    }
}
