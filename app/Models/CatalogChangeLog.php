<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
class CatalogChangeLog extends Model
{
    protected $fillable = [
        'catalog_change_batch_id',
        'catalog_edition_id',
        'stamp_id',
        'stamp_series_id',
        'condition_id',
        'record_type',
        'record_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * De batch waartoe deze wijziging behoort.
     *
     * @return BelongsTo<CatalogChangeBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            CatalogChangeBatch::class,
            'catalog_change_batch_id'
        );
    }

    /**
     * De cataloguseditie waarop de wijziging betrekking heeft.
     *
     * @return BelongsTo<CatalogEdition, $this>
     */
    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    /**
     * De postzegel waarop de wijziging betrekking heeft.
     *
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    /**
     * De postzegelserie waarop de wijziging betrekking heeft.
     *
     * @return BelongsTo<StampSeries, $this>
     */
    public function stampSeries(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class);
    }

    /**
     * De waarderingscategorie waarop de wijziging betrekking heeft.
     *
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
