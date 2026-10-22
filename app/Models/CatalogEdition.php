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

    /**
     * @return BelongsTo<Catalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    /**
     * @return HasMany<StampCatalogEntry, $this>
     */
    public function stampEntries(): HasMany
    {
        return $this->hasMany(StampCatalogEntry::class);
    }

    /**
     * De catalogusregistraties van bijzondere verzamelobjecten.
     *
     * @return HasMany<CollectibleCatalogEntry, $this>
     */
    public function collectibleEntries(): HasMany
    {
        return $this->hasMany(CollectibleCatalogEntry::class);
    }

    /**
     * @return HasManyThrough<StampCatalogValue, StampCatalogEntry, $this>
     */
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

    /**
     * De cataloguswaarden van bijzondere verzamelobjecten
     * binnen deze cataloguseditie.
     *
     * @return HasManyThrough<CollectibleCatalogValue, CollectibleCatalogEntry, $this>
     */
    public function collectibleValues(): HasManyThrough
    {
        return $this->hasManyThrough(
            CollectibleCatalogValue::class,
            CollectibleCatalogEntry::class,
            'catalog_edition_id',
            'collectible_catalog_entry_id',
            'id',
            'id'
        );
    }

    /**
     * @return HasMany<SeriesCatalogValue, $this>
     */
    public function seriesValues(): HasMany
    {
        return $this->hasMany(SeriesCatalogValue::class);
    }

    /**
     * @return HasMany<UserCatalog, $this>
     */
    public function userCatalogs(): HasMany
    {
        return $this->hasMany(UserCatalog::class);
    }
}
