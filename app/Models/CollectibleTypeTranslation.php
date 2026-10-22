<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectibleTypeTranslation extends Model
{
    protected $fillable = [
        'collectible_type_id',
        'locale',
        'name',
    ];

    /**
     * @return BelongsTo<CollectibleType, $this>
     */
    public function collectibleType(): BelongsTo
    {
        return $this->belongsTo(CollectibleType::class);
    }
}
