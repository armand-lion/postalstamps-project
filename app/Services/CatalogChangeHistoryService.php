<?php

namespace App\Services;

use App\Models\CatalogChangeBatch;
use App\Models\CatalogChangeLog;
use App\Models\CatalogEdition;
use App\Models\SeriesCatalogValue;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CatalogChangeHistoryService
{
    /**
     * De momenteel actieve wijzigingsbatch.
     */
    private ?CatalogChangeBatch $activeBatch = null;

    /**
     * Stel de actieve wijzigingsbatch in.
     */
    public function setActiveBatch(?CatalogChangeBatch $batch): void
    {
        $this->activeBatch = $batch;
    }

    /**
     * Haal de actieve wijzigingsbatch op.
     */
    public function getActiveBatch(): ?CatalogChangeBatch
    {
        return $this->activeBatch;
    }

    /**
     * Wis de actieve wijzigingsbatch.
     */
    public function clearActiveBatch(): void
    {
        $this->activeBatch = null;
    }

    /**
     * Maak een nieuwe batch aan voor een catalogusbewerking.
     */
    public function createBatch(
        CatalogEdition $catalogEdition,
        string $operation,
        ?User $user = null,
        ?string $description = null
    ): CatalogChangeBatch {
        $catalogEdition->loadMissing('catalog');

        return CatalogChangeBatch::create([
            'catalog_edition_id' => $catalogEdition->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'operation' => $operation,
            'description' => $description,
            'catalog_name' => $catalogEdition->catalog->name,
            'catalog_edition_name' => $catalogEdition->edition_name,
        ]);
    }

    /**
     * Registreer één wijziging binnen een bestaande batch.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function recordChange(
        CatalogChangeBatch $batch,
        Model $record,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): CatalogChangeLog {
        if (! in_array($action, ['created', 'updated', 'deleted'], true)) {
            throw new InvalidArgumentException(
                'Ongeldige actie voor catalogusgeschiedenis.'
            );
        }

        $catalogEditionId = null;
        $stampId = null;
        $stampSeriesId = null;
        $conditionId = null;

        if ($record instanceof StampCatalogEntry) {
            $recordType = 'stamp_catalog_entry';

            $catalogEditionId = $record->catalog_edition_id;
            $stampId = $record->stamp_id;
        } elseif ($record instanceof StampCatalogValue) {
            $recordType = 'stamp_catalog_value';

            $catalogEntry = $record->catalogEntry;

            if ($catalogEntry === null) {
                throw new InvalidArgumentException(
                    'De catalogusvermelding ontbreekt bij deze cataloguswaarde.'
                );
            }

            $catalogEditionId = $catalogEntry->catalog_edition_id;
            $stampId = $catalogEntry->stamp_id;
            $conditionId = $record->condition_id;
        } elseif ($record instanceof SeriesCatalogValue) {
            $recordType = 'series_catalog_value';

            $catalogEditionId = $record->catalog_edition_id;
            $stampSeriesId = $record->stamp_series_id;
            $conditionId = $record->condition_id;
        } else {
            throw new InvalidArgumentException(
                'Dit model wordt niet ondersteund door de catalogusgeschiedenis.'
            );
        }

        if ($catalogEditionId !== $batch->catalog_edition_id) {
            throw new InvalidArgumentException(
                'De cataloguseditie van het record komt niet overeen met de batch.'
            );
        }

        return CatalogChangeLog::create([
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $catalogEditionId,
            'stamp_id' => $stampId,
            'stamp_series_id' => $stampSeriesId,
            'condition_id' => $conditionId,
            'record_type' => $recordType,
            'record_id' => $record->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
