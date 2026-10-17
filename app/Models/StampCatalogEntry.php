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

    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
    }
}
