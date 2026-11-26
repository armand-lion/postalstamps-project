<?php

namespace App\Observers;

use App\Models\SeriesCatalogValue;
use App\Services\CatalogChangeHistoryService;

class SeriesCatalogValueObserver
{
    /**
     * Handle the SeriesCatalogValue "created" event.
     */
    public function created(SeriesCatalogValue $seriesCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $seriesCatalogValue,
            'created',
            null,
            $seriesCatalogValue->getAttributes()
        );
    }

    /**
     * Handle the SeriesCatalogValue "updated" event.
     */
    public function updated(SeriesCatalogValue $seriesCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $changes = $seriesCatalogValue->getChanges();

        $previous = $seriesCatalogValue->getPrevious();

        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changes as $attribute => $newValue) {
            $oldValues[$attribute] = $previous[$attribute] ?? null;
            $newValues[$attribute] = $newValue;
        }

        $history->recordChange(
            $batch,
            $seriesCatalogValue,
            'updated',
            $oldValues,
            $newValues
        );
    }

    /**
     * Handle the SeriesCatalogValue "deleted" event.
     */
    public function deleted(SeriesCatalogValue $seriesCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $seriesCatalogValue,
            'deleted',
            $seriesCatalogValue->getAttributes(),
            null
        );
    }

    /**
     * Handle the SeriesCatalogValue "restored" event.
     */
    public function restored(SeriesCatalogValue $seriesCatalogValue): void
    {
        //
    }

    /**
     * Handle the SeriesCatalogValue "force deleted" event.
     */
    public function forceDeleted(SeriesCatalogValue $seriesCatalogValue): void
    {
        //
    }
}
