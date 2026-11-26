<?php

namespace App\Observers;

use App\Models\StampCatalogValue;
use App\Services\CatalogChangeHistoryService;

class StampCatalogValueObserver
{
    /**
     * Handle the StampCatalogValue "created" event.
     */
    public function created(StampCatalogValue $stampCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $stampCatalogValue,
            'created',
            null,
            $stampCatalogValue->getAttributes()
        );
    }

    /**
     * Handle the StampCatalogValue "updated" event.
     */
    public function updated(StampCatalogValue $stampCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $changes = $stampCatalogValue->getChanges();

        $previous = $stampCatalogValue->getPrevious();

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
            $stampCatalogValue,
            'updated',
            $oldValues,
            $newValues
        );
    }

    /**
     * Handle the StampCatalogValue "deleted" event.
     */
    public function deleted(StampCatalogValue $stampCatalogValue): void
    {
        $history = app(CatalogChangeHistoryService::class);

        $batch = $history->getActiveBatch();

        if ($batch === null) {
            return;
        }

        $history->recordChange(
            $batch,
            $stampCatalogValue,
            'deleted',
            $stampCatalogValue->getAttributes(),
            null
        );
    }

    /**
     * Handle the StampCatalogValue "restored" event.
     */
    public function restored(StampCatalogValue $stampCatalogValue): void
    {
        //
    }

    /**
     * Handle the StampCatalogValue "force deleted" event.
     */
    public function forceDeleted(StampCatalogValue $stampCatalogValue): void
    {
        //
    }
}
