<?php

namespace App\Observers;

use App\Models\StampCatalogEntry;
use App\Services\CatalogChangeHistoryService;

class StampCatalogEntryObserver
{
    /**
     * Handle the StampCatalogEntry "created" event.
     */
    public function created(StampCatalogEntry $stampCatalogEntry): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $stampCatalogEntry,
            'created',
            null,
            $stampCatalogEntry->getAttributes()
        );
    }

    /**
     * Handle the StampCatalogEntry "updated" event.
     */
    public function updated(StampCatalogEntry $stampCatalogEntry): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $changes = $stampCatalogEntry->getChanges();
        $previous = $stampCatalogEntry->getPrevious();

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
            $stampCatalogEntry,
            'updated',
            $oldValues,
            $newValues
        );
    }

    /**
     * Handle the StampCatalogEntry "deleted" event.
     */
    public function deleted(StampCatalogEntry $stampCatalogEntry): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $stampCatalogEntry,
            'deleted',
            $stampCatalogEntry->getAttributes(),
            null
        );
    }

    /**
     * Handle the StampCatalogEntry "restored" event.
     */
    public function restored(StampCatalogEntry $stampCatalogEntry): void
    {
        //
    }

    /**
     * Handle the StampCatalogEntry "force deleted" event.
     */
    public function forceDeleted(StampCatalogEntry $stampCatalogEntry): void
    {
        //
    }
}
