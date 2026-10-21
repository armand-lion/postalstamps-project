<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectibleCatalogEntry extends Model
{
    protected $fillable = [
        'catalog_edition_id',
        'collectible_id',
        'catalog_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'catalog_edition_id' => 'integer',
            'collectible_id' => 'integer',
        ];
    }

    /**
     * De cataloguseditie waartoe deze registratie behoort.
     */
    public function catalogEdition(): BelongsTo
    {
        return $this->belongsTo(CatalogEdition::class);
    }

    /**
     * Het verzamelobject waarop deze registratie betrekking heeft.
     */
    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class);
    }

    /**
     * De cataloguswaarden die bij deze catalogusregistratie horen.
     */
    public function catalogValues(): HasMany
    {
        return $this->hasMany(CollectibleCatalogValue::class);
    }
}
