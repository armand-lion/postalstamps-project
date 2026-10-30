<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatermarkTranslation extends Model
{
    protected $fillable = [
        'watermark_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * Het watermerk waartoe deze vertaling behoort.
     *
     * @return BelongsTo<Watermark, $this>
     */
    public function watermark(): BelongsTo
    {
        return $this->belongsTo(Watermark::class);
    }
}
