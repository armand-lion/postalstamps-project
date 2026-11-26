<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCatalogValue extends Model
{
    protected $fillable = [
        'stamp_catalog_entry_id',
        'condition_id',
        'catalog_value',
        'currency',
        'notes',
        'needs_review',
    ];

    protected $attributes = [
        'needs_review' => false,
    ];

    protected function casts(): array
    {
        return [
            'catalog_value' => 'decimal:2',
            'needs_review' => 'boolean',
        ];
    }

    /**
     * Markeer gewijzigde cataloguswaarden opnieuw voor controle.
     */
    protected static function booted(): void
    {
        static::updating(function (StampCatalogValue $catalogValue): void {
            if ($catalogValue->isDirty('catalog_value')) {
                $catalogValue->needs_review = true;
            }
        });
    }

    /**
     * @return BelongsTo<StampCatalogEntry, $this>
     */
    public function catalogEntry(): BelongsTo
    {
        return $this->belongsTo(StampCatalogEntry::class, 'stamp_catalog_entry_id');
    }

    /**
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
