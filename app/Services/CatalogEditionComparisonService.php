<?php

namespace App\Services;

use App\Models\CatalogEdition;

class CatalogEditionComparisonService
{
    /**
     * Bereken de waardeverandering tussen twee cataloguswaarden.
     *
     * @return array{absolute: float|null, percentage: float|null}
     */
    public function calculateValueChange(
        ?float $previousValue,
        ?float $currentValue,
        ?string $previousCurrency,
        ?string $currentCurrency
    ): array {
        $result = [
            'absolute' => null,
            'percentage' => null,
        ];

        // Vergelijk uitsluitend bestaande waarden met dezelfde valuta.
        if (
            $previousValue === null ||
            $currentValue === null ||
            $previousCurrency === null ||
            $currentCurrency === null ||
            $previousCurrency !== $currentCurrency
        ) {
            return $result;
        }

        // Bereken het absolute verschil.
        $absoluteChange = round($currentValue - $previousValue, 2);

        $result['absolute'] = $absoluteChange;

        // Bereken alleen een percentage wanneer de vorige waarde
        // groter is dan nul.
        if ($previousValue > 0) {
            $result['percentage'] = round(
                ($absoluteChange / $previousValue) * 100,
                2
            );
        }

        return $result;
    }

    /**
     * Vergelijk twee edities van dezelfde catalogus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compareEditions(
        CatalogEdition $previousEdition,
        CatalogEdition $currentEdition
    ): array {
        // Beide edities moeten tot dezelfde catalogus behoren.
        if ($previousEdition->catalog_id !== $currentEdition->catalog_id) {
            throw new \InvalidArgumentException(
                'De catalogusedities moeten tot dezelfde catalogus behoren.'
            );
        }

        // Een editie mag niet met zichzelf worden vergeleken.
        if ($previousEdition->id === $currentEdition->id) {
            throw new \InvalidArgumentException(
                'Selecteer twee verschillende catalogusedities.'
            );
        }

        // Haal de catalogusregistraties van de vorige editie op.
        $previousEntries = $previousEdition->stampEntries()
            ->with([
                'stamp.translations',
                'stamp.country.translations',
                'values.condition.translations',
            ])
            ->get()
            ->keyBy('stamp_id');

        // Haal de catalogusregistraties van de huidige editie op.
        $currentEntries = $currentEdition->stampEntries()
            ->with([
                'stamp.translations',
                'stamp.country.translations',
                'values.condition.translations',
            ])
            ->get()
            ->keyBy('stamp_id');

        // Verzamel alle unieke postzegel-ID's uit beide edities.
        $stampIds = $previousEntries->keys()
            ->merge($currentEntries->keys())
            ->unique()
            ->sort()
            ->values();

        // Bereid de vergelijkingsresultaten voor.
        $results = [];

        // Doorloop alle unieke postzegels uit beide edities.
        foreach ($stampIds as $stampId) {
            // Haal de catalogusregistratie uit de vorige editie op.
            $previousEntry = $previousEntries->get($stampId);

            // Haal de catalogusregistratie uit de huidige editie op.
            $currentEntry = $currentEntries->get($stampId);

            // Bepaal de status van de postzegel binnen de vergelijking.
            $status = match (true) {
                $previousEntry === null => 'added',
                $currentEntry === null => 'removed',
                default => 'existing',
            };

            // Haal de algemene postzegelgegevens op.
            $stamp = $currentEntry !== null
                ? $currentEntry->stamp
                : $previousEntry->stamp;

            // Haal de catalogusnummers uit beide edities op.
            $previousCatalogNumber = $previousEntry?->catalog_number;
            $currentCatalogNumber = $currentEntry?->catalog_number;

            // Indexeer de cataloguswaarden van de vorige editie per conditie.
            $previousValues = $previousEntry?->values->keyBy('condition_id') ?? collect();

            // Indexeer de cataloguswaarden van de huidige editie per conditie.
            $currentValues = $currentEntry?->values->keyBy('condition_id') ?? collect();

            // Verzamel alle unieke conditie-ID's uit beide edities.
            $conditionIds = $previousValues->keys()
                ->merge($currentValues->keys())
                ->unique()
                ->sort()
                ->values();

            // Bereid de waardevergelijkingen per conditie voor.
            $conditionResults = [];

            // Doorloop alle unieke waarderingscondities van deze postzegel.
            foreach ($conditionIds as $conditionId) {
                // Haal de cataloguswaarde uit de vorige editie op.
                $previousValue = $previousValues->get($conditionId);

                // Haal de cataloguswaarde uit de huidige editie op.
                $currentValue = $currentValues->get($conditionId);

                // Haal de algemene conditiegegevens op.
                $condition = $currentValue !== null
                    ? $currentValue->condition
                    : $previousValue->condition;

                // Haal de numerieke cataloguswaarden op.
                $previousAmount = $previousValue?->catalog_value !== null
                    ? (float) $previousValue->catalog_value
                    : null;

                $currentAmount = $currentValue?->catalog_value !== null
                    ? (float) $currentValue->catalog_value
                    : null;

                // Haal de bijbehorende valuta op.
                $previousCurrency = $previousValue?->currency;
                $currentCurrency = $currentValue?->currency;

                // Bereken de absolute en procentuele waardeverandering.
                $valueChange = $this->calculateValueChange(
                    $previousAmount,
                    $currentAmount,
                    $previousCurrency,
                    $currentCurrency
                );

                $absoluteChange = $valueChange['absolute'];
                $percentageChange = $valueChange['percentage'];

                // Bepaal de waardeveranderingsstatus per conditie.
                $valueStatus = match (true) {
                    $previousAmount === null && $currentAmount !== null => 'added',
                    $previousAmount !== null && $currentAmount === null => 'removed',
                    $absoluteChange === null => 'not_comparable',
                    $absoluteChange > 0 => 'increased',
                    $absoluteChange < 0 => 'decreased',
                    default => 'unchanged',
                };

                // Voeg het vergelijkingsresultaat van deze conditie toe.
                $conditionResults[] = [
                    'condition_id' => $conditionId,
                    'condition' => $condition,
                    'previous_value' => $previousAmount,
                    'current_value' => $currentAmount,
                    'previous_currency' => $previousCurrency,
                    'current_currency' => $currentCurrency,
                    'absolute_change' => $absoluteChange,
                    'percentage_change' => $percentageChange,
                    'status' => $valueStatus,
                ];
            }

            // Voeg het volledige vergelijkingsresultaat van deze postzegel toe.
            $results[] = [
                'stamp_id' => $stampId,
                'stamp' => $stamp,
                'previous_catalog_number' => $previousCatalogNumber,
                'current_catalog_number' => $currentCatalogNumber,
                'status' => $status,
                'conditions' => $conditionResults,
            ];
        }

        return $results;
    }
}
