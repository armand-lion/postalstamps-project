<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectibleImage extends Model
{
    protected $fillable = [
        'collectible_id',
        'path',
        'image_type',
        'alt_text',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'collectible_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Het verzamelobject waartoe deze afbeelding behoort.
     */
    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class);
    }
}
