<?php

namespace App\Services;

use App\Models\Stamp;

class StampBulkSelectionService
{
    /**
     * Bepaal de gebruikersgebonden sessiesleutel.
     */
    private function sessionKey(int $userId): string
    {
        return "stamp_bulk_selection.{$userId}";
    }

    /**
     * Haal alle bestaande geselecteerde postzegel-ID's op.
     *
     * @return array<int, int>
     */
    public function getSelectedIds(int $userId): array
    {
        $selectedIds = session()->get($this->sessionKey($userId), []);

        if ($selectedIds === []) {
            return [];
        }

        $existingIds = Stamp::query()
            ->whereIntegerInRaw('id', $selectedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($existingIds) !== count($selectedIds)) {
            $this->store($userId, $existingIds);
        }

        return $existingIds;
    }

    /**
     * Sla de selectie op zonder dubbele ID's.
     *
     * @param  array<int, int>  $stampIds
     */
    private function store(int $userId, array $stampIds): void
    {
        session()->put(
            $this->sessionKey($userId),
            array_values(array_unique($stampIds))
        );
    }

    /**
     * Voeg postzegels toe aan de bestaande selectie.
     *
     * @param  array<int, int>  $stampIds
     */
    public function add(int $userId, array $stampIds): void
    {
        $this->store(
            $userId,
            array_merge($this->getSelectedIds($userId), $stampIds)
        );
    }

    /**
     * Verwijder postzegels uit de bestaande selectie.
     *
     * @param  array<int, int>  $stampIds
     */
    public function remove(int $userId, array $stampIds): void
    {
        $this->store(
            $userId,
            array_diff($this->getSelectedIds($userId), $stampIds)
        );
    }

    /**
     * Controleer of een postzegel geselecteerd is.
     */
    public function isSelected(int $userId, int $stampId): bool
    {
        return in_array(
            $stampId,
            $this->getSelectedIds($userId),
            true
        );
    }

    /**
     * Tel het aantal geselecteerde postzegels.
     */
    public function count(int $userId): int
    {
        return count($this->getSelectedIds($userId));
    }

    /**
     * Wis de volledige selectie.
     */
    public function clear(int $userId): void
    {
        session()->forget($this->sessionKey($userId));
    }
}
