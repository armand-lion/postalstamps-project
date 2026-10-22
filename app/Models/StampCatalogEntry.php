<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampCatalogEntry extends Model
{
    protected $fillable = [
        'catalog_edition_id',
        'stamp_id',
        'catalog_number',
        'notes',
    ];

    /**
     * @return BelongsTo<CatalogEdition, $this>
     */
    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    /**
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    /**
     * @return HasMany<StampCatalogValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
    }
}
