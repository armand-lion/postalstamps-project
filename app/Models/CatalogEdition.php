<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function stampValues(): HasMany
    {
        return $this->hasMany(StampCatalogValue::class);
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
