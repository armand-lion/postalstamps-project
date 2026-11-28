<?php

namespace App\Services;

use App\Models\Stamp;
use App\Models\StampChangeBatch;
use App\Models\StampChangeLog;
use App\Models\User;

class StampChangeHistoryService
{
    /**
     * Maak een nieuwe wijzigingsbatch aan.
     *
     * @param  array<string, mixed>  $operations
     */
    public function createBatch(
        User $user,
        string $operation,
        ?string $description,
        array $operations
    ): StampChangeBatch {
        return StampChangeBatch::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'operation' => $operation,
            'description' => $description,
            'stamp_count' => 0,
            'operations' => $operations,
        ]);
    }

    /**
     * Registreer de wijzigingen van een afzonderlijke postzegel.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function recordChange(
        StampChangeBatch $batch,
        Stamp $stamp,
        array $oldValues,
        array $newValues
    ): StampChangeLog {
        return $batch->logs()->create([
            'stamp_id' => $stamp->id,
            'stamp_name' => $stamp->name,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * Rond de wijzigingsbatch af.
     */
    public function finalizeBatch(StampChangeBatch $batch): void
    {
        $stampCount = $batch->logs()->count();

        if ($stampCount === 0) {
            $batch->delete();

            return;
        }

        $batch->update([
            'stamp_count' => $stampCount,
        ]);
    }
}
