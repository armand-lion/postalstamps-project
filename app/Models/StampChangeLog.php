<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampChangeLog extends Model
{
    protected $fillable = [
        'stamp_change_batch_id',
        'stamp_id',
        'stamp_name',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * De batch waartoe deze wijziging behoort.
     *
     * @return BelongsTo<StampChangeBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            StampChangeBatch::class,
            'stamp_change_batch_id'
        );
    }

    /**
     * De postzegel waarop deze wijziging betrekking heeft.
     *
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }
}
