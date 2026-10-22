<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesCatalogValue extends Model
{
    protected $fillable = [
        'catalog_edition_id',
        'stamp_series_id',
        'condition_id',
        'catalog_number',
        'catalog_value',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'catalog_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CatalogEdition, $this>
     */
    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    /**
     * @return BelongsTo<StampSeries, $this>
     */
    public function stampSeries(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class);
    }

    /**
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
