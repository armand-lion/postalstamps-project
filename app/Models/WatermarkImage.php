<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatermarkImage extends Model
{
    protected $fillable = [
        'watermark_id',
        'path',
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
     * Het watermerk waartoe deze afbeelding behoort.
     *
     * @return BelongsTo<Watermark, $this>
     */
    public function watermark(): BelongsTo
    {
        return $this->belongsTo(Watermark::class);
    }
}
