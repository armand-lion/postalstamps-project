<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectibleCatalogValue extends Model
{
    protected $fillable = [
        'collectible_catalog_entry_id',
        'condition_id',
        'catalog_value',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'collectible_catalog_entry_id' => 'integer',
            'condition_id' => 'integer',
            'catalog_value' => 'decimal:2',
        ];
    }

    /**
     * De catalogusregistratie waartoe deze waarde behoort.
     *
     * @return BelongsTo<CollectibleCatalogEntry, $this>
     */
    public function collectibleCatalogEntry(): BelongsTo
    {
        return $this->belongsTo(CollectibleCatalogEntry::class);
    }

    /**
     * De conditie waarop deze cataloguswaarde betrekking heeft.
     *
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
