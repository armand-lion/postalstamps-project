<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesValuation extends Model
{
    protected $fillable = [
        'stamp_series_id',
        'condition_id',
        'valuation_date',
        'value',
        'currency',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valuation_date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function stampSeries(): BelongsTo
    {
        return $this->belongsTo(StampSeries::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
