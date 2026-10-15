<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampValuation extends Model
{
    protected $fillable = [
        'stamp_id',
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

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }
}
