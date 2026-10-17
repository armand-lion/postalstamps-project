<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CatalogEdition extends Model
{
    protected $fillable = [
        'catalog_id',
        'edition_name',
        'edition_year',
        'publication_date',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'edition_year' => 'integer',
            'publication_date' => 'date',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function stampEntries(): HasMany
    {
        return $this->hasMany(StampCatalogEntry::class);
    }

    public function stampValues(): HasManyThrough
    {
        return $this->hasManyThrough(
            StampCatalogValue::class,
            StampCatalogEntry::class,
            'catalog_edition_id',
            'stamp_catalog_entry_id',
            'id',
            'id'
        );
    }

    public function seriesValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    public function userCatalogs(): HasMany
    {
        return $this->hasMany(UserCatalog::class);
    }
}
