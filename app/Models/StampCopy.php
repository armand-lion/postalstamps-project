<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StampCopy extends Model
{
    protected $fillable = [
        'stamp_id',
        'condition_id',
        'acquisition_date',
        'purchase_price',
        'purchase_currency',
        'acquisition_method',
        'watermark',
        'perforation',
        'gum_condition',
        'is_variant',
        'variant_description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'purchase_price' => 'decimal:2',
            'is_variant' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Stamp, $this>
     */
    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }

    /**
     * @return BelongsTo<Condition, $this>
     */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    /**
     * @return HasMany<StampCopyImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(StampCopyImage::class);
    }

    /**
     * @return HasMany<StampCopyValuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(StampCopyValuation::class);
    }
}
