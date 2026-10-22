<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampSeriesTranslation extends Model
{
    protected $fillable = [
        'stamp_series_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * @return BelongsTo<StampSeries, $this>
     */
    public function stampSeries(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class);
    }
}
