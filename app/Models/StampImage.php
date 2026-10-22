<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampImage extends Model
{
    protected $fillable = [
        'stamp_id',
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
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }
}
