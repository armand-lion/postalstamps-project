<?php

namespace App\Services;

use App\Models\CatalogEdition;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CatalogEditionDuplicationService
{
    /**
     * Maak de bestaande cataloguswaarderingsservice beschikbaar.
     */
    public function __construct(
        private readonly CatalogConditionService $catalogConditionService
    ) {}

    /**
     * Controleer of twee catalogusedities geschikt zijn voor duplicatie.
     */
    public function validateEditions(
        CatalogEdition $sourceEdition,
        CatalogEdition $targetEdition
    ): void {
        if ($sourceEdition->id === $targetEdition->id) {
            throw new InvalidArgumentException(
                'Een cataloguseditie kan niet naar zichzelf worden gekopieerd.'
            );
        }

        if ($sourceEdition->catalog_id !== $targetEdition->catalog_id) {
            throw new InvalidArgumentException(
                'De bron- en doeleditie moeten bij dezelfde catalogus horen.'
            );
        }
    }

    /**
     * Tel de postzegelvermeldingen die beschikbaar zijn om te kopiëren.
     */
    public function countSourceEntries(CatalogEdition $sourceEdition): int
    {
        return $sourceEdition->stampEntries()->count();
    }

    /**
     * Tel hoeveel postzegels uit de broneditie
     * al aanwezig zijn in de doeleditie.
     */
    public function countExistingEntries(
        CatalogEdition $sourceEdition,
        CatalogEdition $targetEdition
    ): int {
        $this->validateEditions($sourceEdition, $targetEdition);

        return $targetEdition->stampEntries()
            ->whereIn(
                'stamp_id',
                $sourceEdition->stampEntries()->select('stamp_id')
            )
            ->count();
    }

    /**
     * Bereken hoeveel nieuwe postzegelvermeldingen
     * naar de doeleditie kunnen worden gekopieerd.
     */
    public function countCopyableEntries(
        CatalogEdition $sourceEdition,
        CatalogEdition $targetEdition
    ): int {
        $this->validateEditions($sourceEdition, $targetEdition);

        return $this->countSourceEntries($sourceEdition)
            - $this->countExistingEntries($sourceEdition, $targetEdition);
    }

    /**
     * Kopieer postzegelvermeldingen naar een andere cataloguseditie.
     *
     * Bestaande vermeldingen worden niet overschreven.
     *
     * @return array{copied: int, skipped: int}
     */
    public function copyStampEntries(
        CatalogEdition $sourceEdition,
        CatalogEdition $targetEdition,
        bool $copyValues = false
    ): array {
        $this->validateEditions($sourceEdition, $targetEdition);

        return DB::transaction(function () use ($sourceEdition, $targetEdition, $copyValues) {
            $copied = 0;
            $skipped = 0;

            $sourceEntries = $sourceEdition->stampEntries()
                ->with('stamp', 'values')
                ->get();

            foreach ($sourceEntries as $sourceEntry) {
                $targetEntry = $targetEdition->stampEntries()->firstOrCreate(
                    [
                        'stamp_id' => $sourceEntry->stamp_id,
                    ],
                    [
                        'catalog_number' => $sourceEntry->catalog_number,
                        'notes' => $sourceEntry->notes,
                    ]
                );

                if ($targetEntry->wasRecentlyCreated) {
                    $copied++;

                    if ($copyValues) {
                        $allowedConditionIds = $this->catalogConditionService
                            ->availableConditions(
                                $targetEdition->catalog,
                                $sourceEntry->stamp->country_id,
                                $sourceEntry->stamp->issue_year
                            )
                            ->pluck('id')
                            ->all();
                        foreach ($sourceEntry->values as $sourceValue) {
                            if (! in_array($sourceValue->condition_id, $allowedConditionIds, true)) {
                                continue;
                            }

                            $targetEntry->values()->create([
                                'condition_id' => $sourceValue->condition_id,
                                'catalog_value' => $sourceValue->catalog_value,
                                'currency' => $sourceValue->currency,
                                'notes' => $sourceValue->notes,
                            ]);
                        }
                    }
                } else {
                    $skipped++;
                }
            }

            return [
                'copied' => $copied,
                'skipped' => $skipped,
            ];
        });
    }
}
