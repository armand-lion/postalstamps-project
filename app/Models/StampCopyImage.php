<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCopyImage extends Model
{
    protected $fillable = [
        'stamp_copy_id',
        'path',
        'image_type',
        'alt_text',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StampCopy, $this>
     */
    public function stampCopy(): BelongsTo
    {
        return $this->belongsTo(StampCopy::class);
    }
}
