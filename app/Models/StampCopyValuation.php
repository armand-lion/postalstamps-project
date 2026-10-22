<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCopyValuation extends Model
{
    protected $fillable = [
        'stamp_copy_id',
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

    /**
     * @return BelongsTo<StampCopy, $this>
     */
    public function stampCopy(): BelongsTo
    {
        return $this->belongsTo(StampCopy::class);
    }
}
