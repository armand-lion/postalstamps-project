<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCopyTranslation extends Model
{
    protected $fillable = [
        'stamp_copy_id',
        'locale',
        'variant_description',
    ];

    /**
     * @return BelongsTo<StampCopy, $this>
     */
    public function stampCopy(): BelongsTo
    {
        return $this->belongsTo(StampCopy::class);
    }
}
