<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectibleCopyTranslation extends Model
{
    protected $fillable = [
        'collectible_copy_id',
        'locale',
        'variant_description',
    ];

    /**
     * Het persoonlijke verzamelobject
     * waarbij deze vertaling hoort.
     *
     * @return BelongsTo<CollectibleCopy, $this>
     */
    public function collectibleCopy(): BelongsTo
    {
        return $this->belongsTo(CollectibleCopy::class);
    }
}
